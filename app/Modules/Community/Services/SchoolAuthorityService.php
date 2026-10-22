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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SchoolAuthorityService
{
    public function requestChange(int $registrationId, User $actor, ?string $targetEmail = null, ?int $targetId = null, ?string $newEmail = null): SchoolAuthorityChange
    {
        return DB::transaction(function () use ($registrationId, $actor, $targetEmail, $targetId, $newEmail): SchoolAuthorityChange {
            $registration = SchoolRegistration::whereKey($registrationId)->lockForUpdate()->firstOrFail();
            $school = Community::whereKey($registration->community_id)->where('is_active', true)->lockForUpdate()->firstOrFail();
            abort_unless($actor->is_active && (int) $registration->user_id === (int) $actor->id && $registration->status === 'approved' && $registration->email_verified_at, 403);
            CommunityOfficial::where('community_id', $school->id)->where('user_id', $actor->id)->where('status', 'active')->whereNull('ended_at')->firstOrFail();

            if ($targetId) {
                $targetUser = User::whereKey($targetId)->where('is_active', true)->firstOrFail();
                $resolvedEmail = $targetEmail ?: $targetUser->email;
            } else {
                $resolvedEmail = (string) $targetEmail;
                $targetUser = User::where('email', $resolvedEmail)->first();
                $targetId = $targetUser?->id;
            }

            if ($newEmail !== null && $newEmail !== $registration->contact_email) {
                throw ValidationException::withMessages(['new_email' => 'Ubah email resmi melalui profil sekolah.']);
            }
            abort_if((int) $targetId === (int) $actor->id, 422, 'Pilih akun penanggung jawab pengganti.');
            abort_if($targetUser && ! $targetUser->is_active, 422, 'Akun penerima tidak aktif.');
            abort_if($targetUser && strcasecmp($resolvedEmail, $targetUser->email) !== 0, 422);
            $email = $registration->contact_email;
            SchoolAuthorityChange::where('school_registration_id', $registrationId)->where('status', 'pending')->update(['status' => 'revoked']);
            $token = Str::random(64);
            $change = SchoolAuthorityChange::create([
                'school_registration_id' => $registrationId,
                'requested_by' => $actor->id,
                'target_user_id' => $targetId,
                'target_email' => $resolvedEmail,
                'new_email' => $email,
                'token_hash' => hash('sha256', $token),
                'status' => 'pending',
                'expires_at' => now()->addDays(2),
            ]);
            $url = URL::temporarySignedRoute('panel.school-authority.confirm', $change->expires_at, ['change' => $change->id, 'token' => $token]);
            DB::afterCommit(function () use ($resolvedEmail, $url, $change, $school): void {
                try {
                    app(EmailDelivery::class)->send([
                        'recipient_email' => $resolvedEmail,
                        'subject' => 'Konfirmasi Penanggung Jawab Sekolah: '.$school->name,
                        'body' => 'Penanggung jawab '.$school->name.' saat ini mengajukan pengalihan kewenangan utama kepada Anda. Silakan tinjau dan konfirmasi penerimaan melalui tautan berikut.',
                        'action_url' => $url,
                        'action_label' => 'Tinjau Pengalihan',
                        'type' => 'information',
                    ]);
                } catch (\Throwable) {
                    Log::warning('School authority verification delivery failed.', ['change_id' => $change->id]);
                }
            });

            return $change;
        });
    }

    public function requestOfficialEmailChange(int $registrationId, User $actor, string $newEmail): SchoolAuthorityChange
    {
        return DB::transaction(function () use ($registrationId, $actor, $newEmail): SchoolAuthorityChange {
            $registration = SchoolRegistration::whereKey($registrationId)->lockForUpdate()->firstOrFail();
            $school = Community::whereKey($registration->community_id)->where('subtype', 'school')->where('is_active', true)->lockForUpdate()->firstOrFail();
            $owner = User::whereKey($actor->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
            abort_unless((int) $registration->user_id === (int) $owner->id && $registration->status === 'approved' && $registration->email_verified_at, 403);
            CommunityOfficial::where('community_id', $school->id)->where('user_id', $owner->id)->where('status', 'active')->whereNull('ended_at')->lockForUpdate()->firstOrFail();
            abort_if(strcasecmp($registration->contact_email, $newEmail) === 0, 422, 'Email resmi baru harus berbeda.');
            SchoolAuthorityChange::where('school_registration_id', $registrationId)->where('status', 'pending')->update(['status' => 'revoked']);
            $token = Str::random(64);
            $change = SchoolAuthorityChange::create([
                'school_registration_id' => $registrationId, 'requested_by' => $owner->id,
                'target_user_id' => $owner->id, 'target_email' => $newEmail, 'new_email' => $newEmail,
                'token_hash' => hash('sha256', $token), 'status' => 'pending', 'expires_at' => now()->addHour(),
            ]);
            $url = URL::temporarySignedRoute('panel.school-authority.confirm', $change->expires_at, ['change' => $change->id, 'token' => $token]);
            DB::afterCommit(function () use ($newEmail, $url, $change): void {
                try {
                    app(EmailDelivery::class)->send(['recipient_email' => $newEmail, 'subject' => 'Verifikasi Email Resmi Sekolah', 'body' => 'Konfirmasikan email resmi sekolah yang baru. Email lama tetap berlaku sampai konfirmasi selesai. Masuk memakai akun pengurus yang mengajukan perubahan.', 'action_url' => $url, 'action_label' => 'Verifikasi Email Resmi', 'type' => 'information']);
                } catch (\Throwable) {
                    Log::warning('School official email verification delivery failed.', ['change_id' => $change->id]);
                }
            });

            return $change;
        });
    }

    public function isEmailChange(SchoolAuthorityChange $change): bool
    {
        return (int) $change->target_user_id === (int) $change->requested_by
            && $change->target_email === $change->new_email;
    }

    public function transferByAdmin(int $communityId, User $admin, string $targetEmail, string $reason): void
    {
        DB::transaction(function () use ($communityId, $admin, $targetEmail, $reason): void {
            $actor = User::whereKey($admin->id)->where('is_active', true)->firstOrFail();
            abort_unless($actor->can('community.verify') && $actor->roles->contains(fn ($role) => in_array($role->panel_access, ['internal', 'both'], true)), 403);
            $registration = SchoolRegistration::where('community_id', $communityId)->where('status', 'approved')->lockForUpdate()->firstOrFail();
            $school = Community::whereKey($communityId)->where('subtype', 'school')->where('is_active', true)->lockForUpdate()->firstOrFail();
            $target = User::where('email', $targetEmail)->where('is_active', true)->lockForUpdate()->first();
            if (! $target || (int) $target->id === (int) $registration->user_id) {
                throw ValidationException::withMessages(['target_email' => 'Pilih akun pengganti aktif yang sudah terdaftar.']);
            }
            $previousId = (int) $registration->user_id;
            $oldOfficial = CommunityOfficial::where('community_id', $communityId)->where('user_id', $previousId)->where('status', 'active')->whereNull('ended_at')->lockForUpdate()->first();
            $replacement = CommunityOfficial::where('community_id', $communityId)->where('user_id', $target->id)->where('status', 'active')->whereNull('ended_at')->lockForUpdate()->first();
            if (! $replacement) {
                $replacement = CommunityOfficial::where('community_id', $communityId)->where('user_id', $target->id)->where('position', 'manager')->whereNull('ended_at')->lockForUpdate()->first();
                if ($replacement) {
                    $replacement->update(['status' => 'active']);
                } else {
                    CommunityOfficial::create(['community_id' => $communityId, 'user_id' => $target->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
                }
            }
            $oldOfficial?->update(['status' => 'inactive', 'ended_at' => today()]);
            $registration->update(['user_id' => $target->id]);
            SchoolAuthorityChange::where('school_registration_id', $registration->id)->where('status', 'pending')->update(['status' => 'revoked']);
            $target->assignRole(Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']));
            ActivityLog::create(['user_id' => $actor->id, 'event' => 'updated', 'auditable_type' => SchoolRegistration::class, 'auditable_id' => $registration->id, 'old_values' => ['user_id' => $previousId], 'new_values' => ['user_id' => $target->id, 'reason' => $reason, 'action' => 'admin_authority_transfer'], 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
            $recipients = array_unique(array_filter([$registration->contact_email, User::find($previousId)?->email, $target->email]));
            DB::afterCommit(function () use ($recipients, $registration): void {
                foreach ($recipients as $recipient) {
                    try {
                        app(EmailDelivery::class)->send(['recipient_email' => $recipient, 'subject' => 'Penanggung Jawab Sekolah Diperbarui oleh Admin', 'body' => 'Admin telah memperbarui penanggung jawab sekolah. Perubahan langsung berlaku; email resmi dan status legalitas tidak berubah. Hubungi admin bila perubahan tidak dikenali.', 'type' => 'information']);
                    } catch (\Throwable) {
                        Log::warning('Admin school handover notice failed.', ['registration_id' => $registration->id]);
                    }
                }
            });
        });
    }

    public function pending(int $id, ?User $actor, string $token): SchoolAuthorityChange
    {
        $change = SchoolAuthorityChange::with(['registration.community', 'requestedBy', 'targetUser'])->findOrFail($id);
        abort_unless($change->status === 'pending' && $change->expires_at->isFuture() && hash_equals($change->token_hash, hash('sha256', $token)), 403);

        if ($actor) {
            abort_unless($actor->is_active, 403);
            $matchesTargetId = $change->target_user_id && (int) $change->target_user_id === (int) $actor->id;
            $matchesTargetEmail = $change->target_email && strtolower((string) $change->target_email) === strtolower((string) $actor->email);

            if ($change->target_user_id || $change->target_email) {
                abort_unless($change->target_user_id ? $matchesTargetId : $matchesTargetEmail, 403);
            }
        }

        return $change;
    }

    public function accept(int $id, User $actor, string $token): void
    {
        DB::transaction(function () use ($id, $actor, $token): void {
            $hint = SchoolAuthorityChange::findOrFail($id);
            $registration = SchoolRegistration::whereKey($hint->school_registration_id)->lockForUpdate()->firstOrFail();
            $change = SchoolAuthorityChange::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->pending($id, $actor, $token);
            $school = Community::whereKey($registration->community_id)->where('is_active', true)->lockForUpdate()->firstOrFail();
            $target = User::whereKey($actor->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
            abort_unless($registration->status === 'approved' && (int) $registration->user_id === (int) $change->requested_by, 409);
            User::whereKey($change->requested_by)->where('is_active', true)->lockForUpdate()->firstOrFail();
            $old = CommunityOfficial::where('community_id', $school->id)->where('user_id', $change->requested_by)->where('status', 'active')->whereNull('ended_at')->lockForUpdate()->firstOrFail();
            $emailOnly = $this->isEmailChange($change);
            abort_unless($emailOnly || $change->new_email === $registration->contact_email, 409, 'Ajukan penyerahan baru tanpa perubahan email.');
            if (! $emailOnly) {
                abort_if((int) $change->requested_by === (int) $target->id, 409);
                $replacement = CommunityOfficial::where('community_id', $school->id)->where('user_id', $target->id)->where('status', 'active')->whereNull('ended_at')->first();
                if (! $replacement) {
                    $replacement = CommunityOfficial::where('community_id', $school->id)->where('user_id', $target->id)->where('position', 'manager')->whereNull('ended_at')->lockForUpdate()->first();
                    if ($replacement) {
                        $replacement->update(['status' => 'active']);
                    } else {
                        CommunityOfficial::create(['community_id' => $school->id, 'user_id' => $target->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
                    }
                }
                $old->update(['status' => 'inactive', 'ended_at' => today()]);
            }
            $oldEmail = $registration->contact_email;
            if ($emailOnly) {
                abort_unless((int) $target->id === (int) $change->requested_by, 403);
                $school->update(['contact_email' => $change->new_email]);
                $registration->update(['contact_email' => $change->new_email, 'email_hash' => hash('sha256', Str::lower($change->new_email)), 'email_verified_at' => now(), 'email_token_hash' => null]);
            } else {
                $registration->update(['user_id' => $target->id]);
            }
            $target->assignRole(Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']));
            $change->update(['status' => 'accepted', 'accepted_at' => now()]);
            ActivityLog::create(['user_id' => $target->id, 'event' => 'updated', 'auditable_type' => SchoolAuthorityChange::class, 'auditable_id' => $change->id, 'new_values' => ['school_id' => $school->id, 'previous_user_id' => $change->requested_by, 'user_id' => $target->id, 'status' => 'accepted']]);
            DB::afterCommit(function () use ($oldEmail, $change): void {
                try {
                    app(EmailDelivery::class)->send(['recipient_email' => $oldEmail, 'subject' => 'Penanggung Jawab Sekolah Diperbarui', 'body' => 'Perubahan penanggung jawab atau email resmi sekolah telah dikonfirmasi dan berlaku. Hubungi admin bila perubahan tidak dikenali.', 'type' => 'information']);
                } catch (\Throwable) {
                    Log::warning('School authority notice failed.', ['change_id' => $change->id]);
                }
            });
        });
    }

    public function reject(int $id, ?User $actor, string $token): void
    {
        DB::transaction(function () use ($id, $actor, $token): void {
            $change = SchoolAuthorityChange::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->pending($id, $actor, $token);
            $change->update(['status' => 'rejected']);

            $registration = SchoolRegistration::find($change->school_registration_id);
            $school = Community::find($registration?->community_id);
            $requester = User::find($change->requested_by);

            if ($requester?->email) {
                DB::afterCommit(function () use ($requester, $change, $school): void {
                    try {
                        app(EmailDelivery::class)->send([
                            'recipient_email' => $requester->email,
                            'subject' => 'Pengalihan Penanggung Jawab Ditolak: '.($school?->name ?? 'Sekolah'),
                            'body' => 'Pengalihan penanggung jawab utama sekolah telah ditolak oleh penerima ('.($change->target_email ?? 'pengganti').'). Wewenang pengelolaan tetap berada pada akun Anda.',
                            'type' => 'information',
                        ]);
                    } catch (\Throwable) {
                        Log::warning('School authority rejection notice failed.', ['change_id' => $change->id]);
                    }
                });
            }
        });
    }
}
