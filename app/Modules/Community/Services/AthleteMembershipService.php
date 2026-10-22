<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Events\SchoolAthleteActivated;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AthleteMembershipService
{
    /**
     * Add or invite athlete into community roster.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function addOrInviteAthlete(
        Community $community,
        int $userId,
        array $attributes = [],
        string $initiatedBy = 'community'
    ): CommunityAthlete {
        return DB::transaction(function () use ($community, $userId, $attributes, $initiatedBy): CommunityAthlete {
            User::query()->where('id', $userId)->lockForUpdate()->first();

            // Lock user's active records in this community to prevent duplicates
            $existing = CommunityAthlete::query()
                ->where('community_id', $community->id)
                ->where('user_id', $userId)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->status === 'active') {
                    throw ValidationException::withMessages([
                        'user_id' => 'Atlet sudah terdaftar aktif di komunitas ini.',
                    ]);
                }

                if ($existing->status === 'pending') {
                    throw ValidationException::withMessages([
                        'user_id' => 'Permohonan keanggotaan atlet ini masih berstatus pending.',
                    ]);
                }
            }

            // Jalur admin/manager yang membuat atlet langsung berstatus active dipaksa menjadi pending.
            // Persetujuan atlet wajib untuk keanggotaan aktif (prinsip dua sumbu).
            // Pengecualian: PerbasiSyncService karena PERBASI adalah sumber kebenaran.
            $status = 'pending';

            return CommunityAthlete::create([
                'community_id' => $community->id,
                'user_id' => $userId,
                'development_level_id' => $attributes['development_level_id'] ?? null,
                'jersey_number' => $attributes['jersey_number'] ?? null,
                'position' => $attributes['position'] ?? null,
                'initiated_by' => $initiatedBy,
                'status' => $status,
                'responded_at' => null,
                'region_status' => 'pending',
                'joined_at' => $attributes['joined_at'] ?? now()->toDateString(),
                'left_at' => null,
            ]);
        });
    }

    /**
     * Athlete approves invitation sent by community manager.
     */
    public function approveByAthlete(CommunityAthlete $athlete, User $user): CommunityAthlete
    {
        $this->assertCanApprove($athlete, $user, 'athlete');
        if ($athlete->status !== 'pending') {
            throw ValidationException::withMessages([
                'athlete' => 'Permohonan keanggotaan sudah tidak berstatus pending.',
            ]);
        }

        return DB::transaction(function () use ($athlete): CommunityAthlete {
            User::query()->where('id', $athlete->user_id)->lockForUpdate()->first();
            $athlete->loadMissing('community');
            $this->assertNoActiveDualMembership($athlete->user_id, $athlete->community, $athlete->id);

            $athlete->update([
                'status' => 'active',
                'region_status' => 'pending',
                'responded_at' => now(),
            ]);

            event(new SchoolAthleteActivated((int) $athlete->community_id, (int) $athlete->user_id));

            return $athlete;
        });
    }

    /**
     * Athlete rejects invitation sent by community manager.
     */
    public function rejectByAthlete(CommunityAthlete $athlete, User $user): CommunityAthlete
    {
        $this->assertCanApprove($athlete, $user, 'athlete');

        if ($athlete->status !== 'pending') {
            throw ValidationException::withMessages([
                'athlete' => 'Permohonan keanggotaan sudah tidak berstatus pending.',
            ]);
        }

        $athlete->update([
            'status' => 'rejected',
            'responded_at' => now(),
        ]);

        return $athlete;
    }

    /**
     * Community manager approves roster application submitted by athlete.
     */
    public function approveByManager(CommunityAthlete $athlete, ?User $actor = null): CommunityAthlete
    {
        $this->assertCanApprove($athlete, $actor, 'manager');
        if ($athlete->status !== 'pending') {
            throw ValidationException::withMessages([
                'athlete' => 'Permohonan keanggotaan sudah tidak berstatus pending.',
            ]);
        }

        return DB::transaction(function () use ($athlete): CommunityAthlete {
            User::query()->where('id', $athlete->user_id)->lockForUpdate()->first();
            $athlete->loadMissing('community');
            $this->assertNoActiveDualMembership($athlete->user_id, $athlete->community, $athlete->id);

            $athlete->update([
                'status' => 'active',
                'region_status' => 'pending',
                'responded_at' => now(),
            ]);

            event(new SchoolAthleteActivated((int) $athlete->community_id, (int) $athlete->user_id));

            return $athlete;
        });
    }

    /**
     * Community manager rejects roster application submitted by athlete.
     */
    public function rejectByManager(CommunityAthlete $athlete, ?User $actor = null): CommunityAthlete
    {
        $this->assertCanApprove($athlete, $actor, 'manager');
        if ($athlete->status !== 'pending') {
            throw ValidationException::withMessages([
                'athlete' => 'Permohonan keanggotaan sudah tidak berstatus pending.',
            ]);
        }

        $athlete->update([
            'status' => 'rejected',
            'responded_at' => now(),
        ]);

        return $athlete;
    }

    /**
     * Guard persetujuan dua sumbu:
     * - initiated_by=community: HANYA atlet terkait yang boleh approve/reject lewat my-requests.
     * - initiated_by=athlete: HANYA pengurus aktif komunitas yang boleh approve/reject lewat panel athletes.
     */
    public function assertCanApprove(CommunityAthlete $athlete, ?User $actor = null, string $approverRole = 'athlete'): void
    {
        if ($athlete->initiated_by === 'community' && $approverRole !== 'athlete') {
            throw new DomainException('Undangan dari komunitas hanya dapat disetujui atau ditolak oleh atlet terkait.');
        }

        if ($athlete->initiated_by === 'athlete') {
            if ($approverRole === 'athlete') {
                throw new DomainException('Permohonan yang diajukan oleh atlet hanya dapat disetujui atau ditolak oleh pengurus komunitas.');
            }

            if ($actor !== null && (int) $athlete->user_id === (int) $actor->id) {
                throw new DomainException('Atlet tidak dapat menyetujui permintaannya sendiri.');
            }
        }

        if ($approverRole === 'athlete' && $actor !== null && (int) $athlete->user_id !== (int) $actor->id) {
            throw new DomainException('Anda tidak memiliki hak untuk memproses permohonan ini.');
        }
    }

    /**
     * Release athlete from community roster (sets status left, records left_at date, keeps history).
     */
    public function release(CommunityAthlete $athlete): CommunityAthlete
    {
        $athlete->update([
            'status' => 'left',
            'left_at' => now()->toDateString(),
        ]);

        return $athlete;
    }

    /**
     * Resend invitation with a 15-minute cooldown guard.
     */
    public function resend(CommunityAthlete $athlete): CommunityAthlete
    {
        if ($athlete->status !== 'pending') {
            throw ValidationException::withMessages([
                'athlete' => 'Hanya permohonan berstatus pending yang dapat dikirim ulang.',
            ]);
        }

        if ($athlete->updated_at && $athlete->updated_at->gt(now()->subMinutes(15))) {
            throw ValidationException::withMessages([
                'resend' => 'Undangan baru dapat dikirim ulang setelah 15 menit.',
            ]);
        }

        $athlete->touch();

        return $athlete;
    }

    /**
     * Ensure athlete is not active in another community with the same sport category.
     *
     * // ponytail: Dual membership checked by sport_category_id equality; upgrade to loan/dual-license rules when multi-sport policy expands.
     */
    public function assertNoActiveDualMembership(int $userId, Community $targetCommunity, ?int $ignoreAthleteId = null): void
    {
        if ($targetCommunity->subtype !== 'club') {
            return;
        }

        $sportCategoryId = $targetCommunity->sport_category_id;

        $query = CommunityAthlete::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('left_at')
            ->where('community_id', '!=', $targetCommunity->id)
            ->whereHas('community', function ($q) use ($sportCategoryId): void {
                $q->where('subtype', 'club');
                if ($sportCategoryId !== null) {
                    $q->where('sport_category_id', $sportCategoryId);
                }
            });

        if ($ignoreAthleteId !== null) {
            $query->where('id', '!=', $ignoreAthleteId);
        }

        $hasDual = $query->lockForUpdate()->exists();

        if ($hasDual) {
            throw ValidationException::withMessages([
                'athlete' => 'Atlet sudah aktif di komunitas lain dengan cabang olahraga yang sama.',
            ]);
        }
    }
}
