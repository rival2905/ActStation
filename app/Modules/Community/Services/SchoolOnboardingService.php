<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\EmailDelivery;
use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolAuthorityChange;
use App\Modules\Community\Models\SchoolRegistration;
use App\Services\EventRegistrationReturn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SchoolOnboardingService implements \App\Contracts\SchoolOnboardingService
{
    public function registerSchool(User $requester, array $data, ?UploadedFile $evidence = null): SchoolRegistration
    {
        try {
            return DB::transaction(function () use ($requester, $data, $evidence): SchoolRegistration {
                $freshRequester = User::whereKey($requester->id)->lockForUpdate()->firstOrFail();
                abort_unless((bool) $freshRequester->is_active, 403);

                if (
                    Community::withTrashed()->where('npsn', $data['npsn'])->lockForUpdate()->exists()
                    || SchoolRegistration::where('npsn', $data['npsn'])->whereIn('status', ['pending', 'approved'])->lockForUpdate()->exists()
                ) {
                    throw ValidationException::withMessages([
                        'npsn' => 'Sekolah sudah terdaftar. Hubungi pengurus atau admin untuk pemulihan akses.',
                    ]);
                }

                $school = Community::create(array_merge($data, [
                    'subtype' => 'school',
                    'sport_category_id' => null,
                    'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(8)),
                    'status' => 'draft',
                    'is_active' => true,
                ]));

                CommunityOfficial::create([
                    'community_id' => $school->id,
                    'user_id' => $freshRequester->id,
                    'position' => 'manager',
                    'status' => 'pending',
                    'started_at' => today(),
                ]);

                $context = session(EventRegistrationReturn::SESSION_KEY, []);
                $registration = SchoolRegistration::create([
                    'community_id' => $school->id,
                    'user_id' => $freshRequester->id,
                    'npsn' => $school->npsn,
                    'contact_email' => $school->contact_email,
                    'email_hash' => hash('sha256', Str::lower($school->contact_email)),
                    'status' => 'pending',
                    'admin_review_status' => 'pending',
                    'evidence_disk' => 'local',
                    'event_id' => $context['event_id'] ?? null,
                    'event_category_id' => $context['category_id'] ?? null,
                ]);

                if ($evidence) {
                    $this->storeEvidence((int) $registration->id, $freshRequester, $evidence);
                }

                $this->resendVerificationEmail((int) $registration->id, $freshRequester);
                $this->audit('created', $registration, null, $registration->only(['id', 'community_id', 'npsn', 'status']), $freshRequester);

                return $registration;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'npsn' => 'Sekolah sudah terdaftar. Hubungi pengurus atau admin untuk pemulihan akses.',
            ]);
        }
    }

    public function owned(int $id, User $actor): SchoolRegistration
    {
        $freshActor = User::whereKey($actor->id)->firstOrFail();
        abort_unless((bool) $freshActor->is_active, 403);

        return SchoolRegistration::with(['community', 'user'])->where('user_id', $freshActor->id)->findOrFail($id);
    }

    public function authorityWorkspace(SchoolRegistration $registration, User $actor): array
    {
        return [
            'pendingChange' => SchoolAuthorityChange::where('school_registration_id', $registration->id)
                ->where('status', 'pending')->where('expires_at', '>', now())->latest('id')->first(),
            'canTransfer' => $actor->is_active
                && (int) $registration->user_id === (int) $actor->id
                && $registration->status === 'approved'
                && $registration->email_verified_at !== null
                && (bool) $registration->community?->is_active
                && CommunityOfficial::where('community_id', $registration->community_id)->where('user_id', $actor->id)
                    ->where('status', 'active')->whereNull('ended_at')->exists(),
        ];
    }

    public function requestCommunityVerification(int $registrationId, User $actor): void
    {
        DB::transaction(function () use ($registrationId, $actor): void {
            $record = SchoolRegistration::where('user_id', $actor->id)->whereKey($registrationId)->lockForUpdate()->firstOrFail();
            $owner = User::whereKey($actor->id)->where('is_active', true)->firstOrFail();
            $school = Community::whereKey($record->community_id)->where('subtype', 'school')->where('is_active', true)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'approved' && $record->email_verified_at, 403);
            CommunityOfficial::where('community_id', $school->id)->where('user_id', $owner->id)->where('status', 'active')->whereNull('ended_at')->lockForUpdate()->firstOrFail();
            try {
                app(CommunityLifecycle::class)->submit($school);
            } catch (\DomainException|\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['legality' => $exception->getMessage()]);
            }
        });
    }

    public function storeEvidence(int $registrationId, User $actor, UploadedFile $evidence): SchoolRegistration
    {
        $freshActor = User::whereKey($actor->id)->firstOrFail();
        abort_unless((bool) $freshActor->is_active, 403);

        $allowedMimes = ['application/pdf', 'image/png', 'image/jpeg'];
        $actualMime = (string) $evidence->getMimeType();
        if (! in_array($actualMime, $allowedMimes, true)) {
            throw ValidationException::withMessages(['evidence' => 'Berkas bukti otorisasi harus berupa PDF, PNG, atau JPG yang valid.']);
        }

        $newPath = null;
        try {
            return DB::transaction(function () use ($registrationId, $freshActor, $evidence, &$newPath): SchoolRegistration {
                $record = SchoolRegistration::where('user_id', $freshActor->id)
                    ->whereKey($registrationId)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless(in_array($record->status, ['pending', 'rejected'], true), 409);

                $school = Community::whereKey($record->community_id)->lockForUpdate()->firstOrFail();
                abort_unless((bool) $school->is_active && $school->subtype === 'school' && $school->npsn === $record->npsn, 409);

                $newPath = $evidence->store('school-authorizations/'.$registrationId, 'local');
                abort_unless(is_string($newPath), 500);

                abort_unless(
                    ! str_contains($newPath, '..')
                    && ! str_contains($newPath, "\0")
                    && dirname($newPath) === 'school-authorizations/'.$registrationId
                    && str_starts_with($newPath, 'school-authorizations/'.$registrationId.'/'),
                    500
                );

                $old = $record->evidence_path;
                $record->update([
                    'evidence_path' => $newPath,
                    'evidence_disk' => 'local',
                    'status' => 'pending',
                    'admin_review_status' => 'pending',
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                    'rejection_reason' => null,
                ]);

                if ($old && $old !== $newPath && str_starts_with($old, 'school-authorizations/'.$registrationId.'/')) {
                    DB::afterCommit(static fn () => Storage::disk('local')->delete($old));
                }

                // ponytail: sync to polymorphic documents for admin visibility
                $school->documents()->updateOrCreate(
                    ['category' => 'institution_decree'],
                    [
                        'name' => 'Bukti Kewenangan Sekolah',
                        'file_path' => $newPath,
                        'status' => 'pending',
                        'is_public' => false,
                    ]
                );

                $this->audit('updated', $record, ['evidence_path' => $old], ['evidence_path' => $newPath, 'status' => 'pending'], $freshActor);

                return $record;
            });
        } catch (\Throwable $e) {
            if ($newPath && Storage::disk('local')->exists($newPath)) {
                Storage::disk('local')->delete($newPath);
            }
            throw $e;
        }
    }

    public function downloadEvidence(int $registrationId, User $actor): mixed
    {
        $freshActor = User::whereKey($actor->id)->firstOrFail();
        abort_unless((bool) ($freshActor->is_active && $freshActor->can('community.verify')), 403);

        $record = SchoolRegistration::whereKey($registrationId)->firstOrFail();
        $path = $record->evidence_path;

        abort_unless(
            $record->evidence_disk === 'local'
            && is_string($path)
            && ! str_contains($path, '..')
            && ! str_contains($path, "\0")
            && dirname($path) === 'school-authorizations/'.$registrationId
            && str_starts_with($path, 'school-authorizations/'.$registrationId.'/')
            && Storage::disk('local')->exists($path),
            404
        );

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'png', 'jpg', 'jpeg'];
        abort_unless(in_array($ext, $allowedExts, true), 404);

        $realPath = Storage::disk('local')->path($path);
        $realRoot = realpath(Storage::disk('local')->path(''));
        $resolvedPath = realpath($realPath);
        abort_unless($resolvedPath && $realRoot && str_starts_with($resolvedPath, $realRoot), 404);

        return Storage::disk('local')->download($path, 'otorisasi-sekolah-'.$registrationId.'.'.$ext);
    }

    public function resendVerificationEmail(int $registrationId, User $requester): void
    {
        DB::transaction(function () use ($registrationId, $requester): void {
            $freshRequester = User::whereKey($requester->id)->lockForUpdate()->firstOrFail();
            abort_unless((bool) $freshRequester->is_active, 403);

            $registration = SchoolRegistration::where('user_id', $freshRequester->id)
                ->whereKey($registrationId)
                ->lockForUpdate()
                ->firstOrFail();

            $school = Community::whereKey($registration->community_id)->lockForUpdate()->firstOrFail();
            abort_unless((bool) $school->is_active && $school->subtype === 'school' && $school->npsn === $registration->npsn, 409);

            if ($school->contact_email !== $registration->contact_email) {
                $registration->update([
                    'contact_email' => $school->contact_email,
                    'email_hash' => hash('sha256', Str::lower($school->contact_email)),
                    'email_verified_at' => null,
                    'admin_review_status' => 'pending',
                    'status' => 'pending',
                ]);
            }

            abort_unless($registration->status === 'pending' && $registration->email_verified_at === null, 409);

            if ($registration->expires_at?->gt(now()->addMinutes(59))) {
                throw ValidationException::withMessages([
                    'email' => 'Email verifikasi sudah dikirim. Tunggu 60 detik sebelum mengirim ulang; gunakan tautan dari email terbaru.',
                ]);
            }

            $token = Str::random(64);
            $registration->update([
                'email_token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHour(),
            ]);

            $url = URL::temporarySignedRoute('panel.school-registrations.verify', $registration->expires_at, ['registration' => $registration->id, 'token' => $token]);
            $email = $registration->contact_email;

            DB::afterCommit(static function () use ($email, $url, $registrationId): void {
                try {
                    app(EmailDelivery::class)->send([
                        'recipient_email' => $email,
                        'subject' => 'Verifikasi Email Sekolah — ActStation',
                        'body' => 'Konfirmasikan email resmi sekolah. Verifikasi email tidak menggantikan pemeriksaan kewenangan oleh admin.',
                        'action_url' => $url,
                        'action_label' => 'Konfirmasi Email Sekolah',
                        'type' => 'information',
                    ]);
                } catch (\Throwable) {
                    Log::warning('School verification delivery failed.', ['school_registration_id' => $registrationId]);
                }
            });
        });
    }

    public function consumeEmailVerification(int $registrationId, string $token, User $actor): bool
    {
        return DB::transaction(function () use ($registrationId, $token, $actor): bool {
            $freshActor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless((bool) $freshActor->is_active, 403);

            $registration = SchoolRegistration::where('user_id', $freshActor->id)
                ->whereKey($registrationId)
                ->lockForUpdate()
                ->firstOrFail();

            $school = Community::whereKey($registration->community_id)->lockForUpdate()->firstOrFail();
            abort_unless((bool) $school->is_active && $school->subtype === 'school' && $school->npsn === $registration->npsn && $school->contact_email === $registration->contact_email, 409);

            abort_unless(
                $registration->status === 'pending'
                && $registration->email_verified_at === null
                && $registration->expires_at?->isFuture()
                && $registration->email_token_hash !== null
                && hash_equals($registration->email_token_hash, hash('sha256', $token)),
                403
            );

            $registration->update([
                'email_verified_at' => now(),
                'email_token_hash' => null,
                'expires_at' => null,
            ]);

            $this->audit('updated', $registration, ['email_verified_at' => null], ['email_verified_at' => now()], $freshActor);

            return true;
        });
    }

    public function reviewRegistration(int $registrationId, User $admin, bool $approved, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($registrationId, $admin, $approved, $reason): bool {
            $freshAdmin = User::whereKey($admin->id)->lockForUpdate()->firstOrFail();
            abort_unless((bool) ($freshAdmin->is_active && $freshAdmin->can('community.verify')), 403);

            $registration = SchoolRegistration::whereKey($registrationId)->lockForUpdate()->firstOrFail();
            abort_if((int) $registration->user_id === (int) $freshAdmin->id, 403);
            abort_unless($registration->status === 'pending' && $registration->admin_review_status === 'pending', 409);

            $school = Community::whereKey($registration->community_id)->lockForUpdate()->firstOrFail();
            $requester = User::whereKey($registration->user_id)->lockForUpdate()->firstOrFail();

            abort_unless(
                (bool) $school->is_active
                && (bool) $requester->is_active
                && $school->subtype === 'school'
                && $school->npsn === $registration->npsn
                && $school->contact_email === $registration->contact_email,
                409
            );

            if ($approved && (! $registration->email_verified_at || ! $registration->evidence_path || $registration->evidence_disk !== 'local' || str_contains($registration->evidence_path, '..') || dirname($registration->evidence_path) !== 'school-authorizations/'.$registration->id || ! Storage::disk('local')->exists($registration->evidence_path))) {
                throw ValidationException::withMessages(['evidence' => 'Verifikasi email dan bukti kewenangan sekolah privat wajib tersedia sebelum disetujui.']);
            }

            if ($approved) {
                $official = CommunityOfficial::where('community_id', $school->id)
                    ->where('user_id', $requester->id)
                    ->where('status', 'pending')
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->firstOrFail();

                $official->update(['status' => 'active']);
                $requester->assignRole(Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']));
            }

            $oldStatus = $registration->status;
            $registration->update([
                'status' => $approved ? 'approved' : 'rejected',
                'admin_review_status' => $approved ? 'approved' : 'rejected',
                'reviewed_by' => $freshAdmin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $approved ? null : $reason,
            ]);

            $this->audit('updated', $registration, ['status' => $oldStatus], ['status' => $approved ? 'approved' : 'rejected'], $freshAdmin);

            return true;
        });
    }

    public function resolveEventResumptionUrl(int $registrationId): ?string
    {
        $registration = SchoolRegistration::findOrFail($registrationId);
        if ($registration->status !== 'approved' || ! $registration->event_id) {
            return null;
        }
        $request = request();
        $return = app(EventRegistrationReturn::class);
        $return->clear($request);
        $return->remember($request, (int) $registration->event_id, $registration->event_category_id ? (int) $registration->event_category_id : null);

        return $return->destination($request);
    }

    protected function audit(string $event, Model $model, ?array $old = null, ?array $new = null, ?User $actor = null): void
    {
        try {
            if (! class_exists(ActivityLog::class)) {
                return;
            }

            $user = $actor ?? auth()->user();
            $roleContext = ($user && method_exists($user, 'roles')) ? $user->roles->first()?->name : null;

            ActivityLog::create([
                'user_id' => $user?->getAuthIdentifier(),
                'event' => $event,
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => $model->getKey(),
                'role_context' => $roleContext,
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()?->ip(),
                'user_agent' => request() ? substr((string) request()->userAgent(), 0, 255) : null,
            ]);
        } catch (\Throwable) {
            // Safe non-blocking audit
        }
    }
}
