<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\EmailDelivery;
use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolExtracurricular;
use App\Modules\Community\Models\SchoolExtracurricularAppointment;
use App\Modules\Community\Models\SportsPersonnelRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SchoolExtracurricularPersonnelService
{
    public function scope(User $user, int $communityId, int $unitId): SchoolExtracurricular
    {
        if (! $user->is_active) {
            abort(403, 'Pengguna tidak aktif.');
        }

        $community = Community::where('id', $communityId)->firstOrFail();
        if (! $community->is_active || $community->subtype !== 'school') {
            abort(403, 'Komunitas bukan sekolah aktif.');
        }

        $unit = SchoolExtracurricular::where('id', $unitId)
            ->where('community_id', $community->id)
            ->firstOrFail();
        $unit->setAttribute('name', DB::table('sport_categories')->where('id', $unit->sport_category_id)->value('name'));

        return $unit;
    }

    public function authorize(SchoolExtracurricular $unit, User $user): void
    {
        if (! $user->is_active) {
            abort(403, 'Pengguna tidak aktif.');
        }

        $isOfficial = CommunityOfficial::where('community_id', $unit->community_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->exists();

        $hasUnitPermission = SchoolExtracurricularAppointment::where('school_extracurricular_id', $unit->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('can_manage_personnel', true)
            ->whereNull('ended_at')
            ->exists();

        if (! $isOfficial && ! $hasUnitPermission) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengelola personel unit ini.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function indexData(SchoolExtracurricular $unit, User $user): array
    {
        $unit->loadMissing(['community']);

        $appointments = SchoolExtracurricularAppointment::with(['user', 'role', 'invitedBy', 'replaces.user'])
            ->where('school_extracurricular_id', $unit->id)
            ->orderByDesc('id')
            ->get();

        $roles = SportsPersonnelRole::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $activeAppointments = $appointments->where('status', 'active');

        return [
            'unit' => $unit,
            'community' => $unit->community,
            'appointments' => $appointments,
            'roles' => $roles,
            'activeAppointments' => $activeAppointments,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{appointment: SchoolExtracurricularAppointment, token: string, url: string}
     */
    public function invite(SchoolExtracurricular $unit, User $user, array $validated): array
    {
        return DB::transaction(function () use ($unit, $user, $validated): array {
            $lockedCommunity = Community::where('id', $unit->community_id)
                ->where('is_active', true)
                ->where('subtype', 'school')
                ->lockForUpdate()
                ->firstOrFail();

            $actor = User::where('id', $user->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUnit = SchoolExtracurricular::where('id', $unit->id)
                ->where('community_id', $lockedCommunity->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorize($lockedUnit, $actor);

            $targetUserId = (int) ($validated['target_user_id'] ?? $validated['user_id'] ?? 0);
            $targetUser = User::where('id', $targetUserId)->where('is_active', true)->first();
            if (! $targetUser) {
                abort(422, 'Target pengguna tidak aktif.');
            }

            $role = SportsPersonnelRole::where('id', $validated['sports_personnel_role_id'])->where('is_active', true)->first();
            if (! $role) {
                abort(422, 'Peran personel tidak aktif.');
            }

            $hasActiveAssignment = SchoolExtracurricularAppointment::where('school_extracurricular_id', $lockedUnit->id)
                ->where('user_id', $targetUser->id)
                ->where('sports_personnel_role_id', $role->id)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->exists();
            if ($hasActiveAssignment) {
                abort(422, 'Pengguna sudah memiliki penugasan aktif untuk peran ini.');
            }

            $canManage = ! empty($validated['can_manage_personnel']);
            $replacesId = ! empty($validated['replaces_id']) ? (int) $validated['replaces_id'] : null;

            if ($replacesId !== null) {
                $replaced = SchoolExtracurricularAppointment::where('id', $replacesId)
                    ->where('school_extracurricular_id', $lockedUnit->id)
                    ->where('sports_personnel_role_id', $role->id)
                    ->where('status', 'active')
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->first();

                if (! $replaced) {
                    abort(422, 'Penugasan yang digantikan tidak valid atau tidak aktif pada unit ini.');
                }
            }

            $existingPending = SchoolExtracurricularAppointment::where('school_extracurricular_id', $lockedUnit->id)
                ->where('user_id', $targetUser->id)
                ->where('sports_personnel_role_id', $role->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            $rawToken = Str::random(64);
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = now()->addDays(7);

            if ($existingPending) {
                $existingPending->update([
                    'can_manage_personnel' => $canManage,
                    'invited_by' => $actor->id,
                    'replaces_id' => $replacesId,
                    'token_hash' => $tokenHash,
                    'expires_at' => $expiresAt,
                ]);
                $appointment = $existingPending;
            } else {
                $appointment = SchoolExtracurricularAppointment::create([
                    'school_extracurricular_id' => $lockedUnit->id,
                    'user_id' => $targetUser->id,
                    'sports_personnel_role_id' => $role->id,
                    'status' => 'pending',
                    'can_manage_personnel' => $canManage,
                    'invited_by' => $actor->id,
                    'replaces_id' => $replacesId,
                    'token_hash' => $tokenHash,
                    'expires_at' => $expiresAt,
                ]);
            }

            $inviteUrl = route('panel.schools.extracurriculars.personnel.accept.show', [
                'community' => $unit->community_id,
                'extracurricular' => $unit->id,
                'appointment' => $appointment->id,
                'token' => $rawToken,
            ]);

            DB::afterCommit(function () use ($unit, $appointment, $inviteUrl, $role, $targetUser): void {
                try {
                    $registration = DB::table('school_registrations')
                        ->where('community_id', $unit->community_id)
                        ->whereNotNull('email_verified_at')
                        ->whereNotNull('reviewed_at')
                        ->where('status', 'approved')
                        ->first();

                    if ($registration && app()->bound(EmailDelivery::class)) {
                        app(EmailDelivery::class)->send([
                            'recipient_email' => $targetUser->email,
                            'subject' => 'Undangan Personel Ekstrakurikuler',
                            'body' => "Undangan penugasan personel baru untuk peran {$role->name}.\nTautan: {$inviteUrl}",
                            'action_url' => $inviteUrl,
                            'action_label' => 'Terima Undangan',
                            'type' => 'extracurricular_personnel_invite',
                        ]);
                    }
                } catch (Throwable $e) {
                    Log::warning('Email delivery failed for personnel invitation.', [
                        'unit_id' => $unit->id,
                        'appointment_id' => $appointment->id,
                    ]);
                }
            });

            return [
                'appointment' => $appointment,
                'token' => $rawToken,
                'url' => $inviteUrl,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function showAccept(SchoolExtracurricular $unit, SchoolExtracurricularAppointment $appointment, User $user, string $token): array
    {
        if ($appointment->school_extracurricular_id !== $unit->id) {
            abort(404, 'Penugasan tidak ditemukan pada unit ini.');
        }

        if ($appointment->user_id !== $user->id) {
            abort(403, 'Hanya pengguna yang dituju yang dapat melihat undangan ini.');
        }

        if (! hash_equals((string) $appointment->token_hash, hash('sha256', $token))) {
            abort(403, 'Token undangan tidak valid.');
        }

        if ($appointment->status !== 'pending' || ($appointment->expires_at && $appointment->expires_at->isPast())) {
            abort(410, 'Undangan sudah tidak berlaku.');
        }

        $appointment->load(['role', 'invitedBy', 'replaces.user']);

        return [
            'unit' => $unit->loadMissing(['community']),
            'community' => $unit->community,
            'appointment' => $appointment,
            'token' => $token,
        ];
    }

    public function accept(SchoolExtracurricular $unit, SchoolExtracurricularAppointment $appointment, User $user, string $token): void
    {
        if ($appointment->school_extracurricular_id !== $unit->id) {
            abort(404, 'Penugasan tidak ditemukan pada unit ini.');
        }

        if ($appointment->user_id !== $user->id) {
            abort(403, 'Hanya pengguna yang dituju yang dapat menerima penugasan ini.');
        }

        DB::transaction(function () use ($unit, $appointment, $user, $token): void {
            $lockedCommunity = Community::where('id', $unit->community_id)
                ->where('is_active', true)
                ->where('subtype', 'school')
                ->lockForUpdate()
                ->firstOrFail();

            $actor = User::where('id', $user->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUnit = SchoolExtracurricular::where('id', $unit->id)
                ->where('community_id', $lockedCommunity->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAppointment = SchoolExtracurricularAppointment::where('id', $appointment->id)
                ->where('school_extracurricular_id', $lockedUnit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals((string) $lockedAppointment->token_hash, hash('sha256', $token))) {
                abort(403, 'Token undangan tidak valid.');
            }

            if ($lockedAppointment->status !== 'pending' || ($lockedAppointment->expires_at && $lockedAppointment->expires_at->isPast())) {
                abort(410, 'Undangan sudah tidak berlaku.');
            }

            $inviter = User::where('id', $lockedAppointment->invited_by)->first();
            if (! $inviter || ! $inviter->is_active) {
                abort(422, 'Pengundang sudah tidak aktif.');
            }

            $inviterOfficial = CommunityOfficial::where('community_id', $lockedCommunity->id)
                ->where('user_id', $inviter->id)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->exists();

            $inviterAppointment = SchoolExtracurricularAppointment::where('school_extracurricular_id', $lockedUnit->id)
                ->where('user_id', $inviter->id)
                ->where('status', 'active')
                ->where('can_manage_personnel', true)
                ->whereNull('ended_at')
                ->exists();

            if (! $inviterOfficial && ! $inviterAppointment) {
                abort(422, 'Pengundang sudah tidak memiliki wewenang.');
            }

            $role = SportsPersonnelRole::where('id', $lockedAppointment->sports_personnel_role_id)
                ->where('is_active', true)
                ->first();
            if (! $role) {
                abort(422, 'Peran personel tidak aktif.');
            }

            $hasActiveAssignment = SchoolExtracurricularAppointment::where('school_extracurricular_id', $lockedUnit->id)
                ->where('user_id', $actor->id)
                ->where('sports_personnel_role_id', $role->id)
                ->where('status', 'active')
                ->where('id', '!=', $lockedAppointment->id)
                ->whereNull('ended_at')
                ->exists();
            if ($hasActiveAssignment) {
                abort(422, 'Pengguna sudah memiliki penugasan aktif untuk peran ini.');
            }

            if ($lockedAppointment->replaces_id) {
                $old = SchoolExtracurricularAppointment::where('id', $lockedAppointment->replaces_id)
                    ->where('school_extracurricular_id', $lockedUnit->id)
                    ->where('sports_personnel_role_id', $role->id)
                    ->where('status', 'active')
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->first();

                if (! $old) {
                    abort(422, 'Penugasan lama yang akan digantikan sudah tidak aktif.');
                }

                if ($old->user_id === $actor->id) {
                    abort(422, 'Tidak dapat menggantikan diri sendiri.');
                }

                $old->update([
                    'status' => 'ended',
                    'ended_at' => now(),
                ]);
            }

            $lockedAppointment->update([
                'status' => 'active',
                'accepted_at' => now(),
                'token_hash' => null,
                'expires_at' => null,
            ]);

            ActivityLog::create([
                'user_id' => $actor->id,
                'event' => 'updated',
                'auditable_type' => $lockedAppointment->getMorphClass(),
                'auditable_id' => $lockedAppointment->id,
                'role_context' => $actor->roles->first()?->name ?? null,
                'new_values' => [
                    'unit_id' => $lockedAppointment->school_extracurricular_id,
                    'user_id' => $lockedAppointment->user_id,
                    'role_id' => $lockedAppointment->sports_personnel_role_id,
                    'status' => $lockedAppointment->status,
                    'replaces_id' => $lockedAppointment->replaces_id,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request() ? substr((string) request()->userAgent(), 0, 255) : null,
            ]);

            DB::afterCommit(function () use ($unit, $lockedAppointment): void {
                try {
                    $registration = DB::table('school_registrations')
                        ->where('community_id', $unit->community_id)
                        ->where('status', 'approved')
                        ->whereNotNull('email_verified_at')
                        ->whereNotNull('reviewed_at')
                        ->first();
                    if ($registration && app()->bound(EmailDelivery::class)) {
                        app(EmailDelivery::class)->send([
                            'recipient_email' => $registration->contact_email,
                            'subject' => 'Perubahan Personel Ekskul — ActStation',
                            'body' => 'Penugasan personel ekskul telah diterima dan berlaku. Pemberitahuan ini bersifat informasional, tidak memerlukan persetujuan sekolah.',
                            'type' => 'information',
                        ]);
                    }
                } catch (Throwable) {
                    Log::warning('School personnel notification failed.', ['appointment_id' => $lockedAppointment->id]);
                }
            });
        });
    }

    public function revoke(SchoolExtracurricular $unit, SchoolExtracurricularAppointment $appointment, User $user): void
    {
        if ($appointment->school_extracurricular_id !== $unit->id) {
            abort(404, 'Penugasan tidak ditemukan pada unit ini.');
        }

        DB::transaction(function () use ($unit, $appointment, $user): void {
            $lockedCommunity = Community::where('id', $unit->community_id)
                ->where('is_active', true)
                ->where('subtype', 'school')
                ->lockForUpdate()
                ->firstOrFail();

            $actor = User::where('id', $user->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUnit = SchoolExtracurricular::where('id', $unit->id)
                ->where('community_id', $lockedCommunity->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorize($lockedUnit, $actor);

            $lockedAppointment = SchoolExtracurricularAppointment::where('id', $appointment->id)
                ->where('school_extracurricular_id', $lockedUnit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAppointment->status !== 'pending') {
                abort(422, 'Hanya undangan berstatus pending yang dapat dibatalkan.');
            }

            $lockedAppointment->update([
                'status' => 'revoked',
                'ended_at' => now(),
                'token_hash' => null,
                'expires_at' => null,
            ]);
        });
    }
}
