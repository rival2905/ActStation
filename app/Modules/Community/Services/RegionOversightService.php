<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\RegionOverseer;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegionOversightService
{
    /**
     * Check if user bypasses region scope (admin/superadmin).
     */
    public function isBypassed(User $user): bool
    {
        return $user->hasRole(['superadmin', 'admin']);
    }

    /**
     * Get list of city IDs the user is assigned to oversee.
     * Returns null if bypassed (all cities allowed).
     *
     * @return list<string>|null
     */
    public function getScopedCityIds(User $user): ?array
    {
        if ($this->isBypassed($user)) {
            return null;
        }

        return RegionOverseer::where('user_id', $user->id)
            ->pluck('city_id')
            ->values()
            ->all();
    }

    /**
     * Check if user can access / oversee a given city ID.
     */
    public function canAccessCity(User $user, ?string $cityId): bool
    {
        if ($this->isBypassed($user)) {
            return true;
        }

        if ($cityId === null || $cityId === '') {
            return false;
        }

        $scoped = $this->getScopedCityIds($user);

        return $scoped !== null && in_array($cityId, $scoped, true);
    }

    /**
     * Apply region scope to community query.
     *
     * @param  Builder<Community>  $query
     * @return Builder<Community>
     */
    public function applyCommunityScope(Builder $query, User $user): Builder
    {
        if ($this->isBypassed($user)) {
            return $query;
        }

        $cityIds = $this->getScopedCityIds($user) ?? [];

        return $query->whereIn('city_id', $cityIds);
    }

    /**
     * Apply region scope to athlete query.
     *
     * @param  Builder<CommunityAthlete>  $query
     * @return Builder<CommunityAthlete>
     */
    public function applyAthleteScope(Builder $query, User $user): Builder
    {
        if ($this->isBypassed($user)) {
            return $query;
        }

        $cityIds = $this->getScopedCityIds($user) ?? [];

        return $query->whereHas('community', function (Builder $q) use ($cityIds): void {
            $q->whereIn('city_id', $cityIds);
        });
    }

    /**
     * Verify single community within user's oversight scope.
     */
    public function verifyCommunity(Community $community, User $user, string $decision, ?string $reason = null): Community
    {
        if (! $this->canAccessCity($user, $community->city_id)) {
            throw new DomainException('Komunitas di luar wilayah pengawasan.');
        }

        if (! in_array($decision, ['verified', 'rejected'], true)) {
            throw new InvalidArgumentException("Keputusan [{$decision}] tidak valid.");
        }

        if ($decision === 'rejected' && empty($reason)) {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
        }

        return DB::transaction(function () use ($community, $user, $decision, $reason): Community {
            $community->status = $decision;
            $community->verified_at = now();
            $community->verified_by = $user->id;
            $community->reject_reason = $decision === 'rejected' ? $reason : null;
            $community->save();

            return $community;
        });
    }

    /**
     * Verify single athlete within user's oversight scope.
     */
    public function verifyAthlete(CommunityAthlete $athlete, User $user, string $decision, ?string $notes = null): CommunityAthlete
    {
        $cityId = $athlete->community?->city_id ?? $athlete->community()->value('city_id');

        if (! $this->canAccessCity($user, $cityId)) {
            throw new DomainException('Atlet di luar wilayah pengawasan.');
        }

        if (! in_array($decision, ['verified', 'rejected'], true)) {
            throw new InvalidArgumentException("Keputusan [{$decision}] tidak valid.");
        }

        return DB::transaction(function () use ($athlete, $user, $decision, $notes): CommunityAthlete {
            $athlete->region_status = $decision;
            $athlete->region_verified_at = now();
            $athlete->region_verified_by = $user->id;
            $athlete->region_notes = $notes;
            $athlete->save();

            return $athlete;
        });
    }

    /**
     * Bulk verify athletes.
     * All IDs MUST be in scope. If any ID is out of scope, entire batch is rejected.
     *
     * @param  list<int|string>  $ids
     */
    public function verifyAthletesBulk(array $ids, User $user, string $decision, ?string $notes = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        if (! in_array($decision, ['verified', 'rejected'], true)) {
            throw new InvalidArgumentException("Keputusan [{$decision}] tidak valid.");
        }

        /** @var Collection<int, CommunityAthlete> $athletes */
        $athletes = CommunityAthlete::with('community')
            ->whereIn('id', $ids)
            ->get();

        if ($athletes->count() !== count(array_unique($ids))) {
            throw new DomainException('Data atlet tidak ditemukan atau tidak lengkap.');
        }

        if (! $this->isBypassed($user)) {
            $scopedCityIds = $this->getScopedCityIds($user) ?? [];
            foreach ($athletes as $athlete) {
                $cityId = $athlete->community?->city_id;
                if ($cityId === null || ! in_array($cityId, $scopedCityIds, true)) {
                    throw new DomainException('Terdapat atlet di luar wilayah pengawasan. Seluruh batch ditolak.');
                }
            }
        }

        return DB::transaction(function () use ($athletes, $decision, $user, $notes): int {
            $now = now();
            foreach ($athletes as $athlete) {
                $athlete->region_status = $decision;
                $athlete->region_verified_at = $now;
                $athlete->region_verified_by = $user->id;
                $athlete->region_notes = $notes;
                $athlete->save();
            }

            return $athletes->count();
        });
    }

    /**
     * Bulk verify communities.
     *
     * @param  list<int|string>  $ids
     */
    public function verifyCommunitiesBulk(array $ids, User $user, string $decision, ?string $reason = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        if (! in_array($decision, ['verified', 'rejected'], true)) {
            throw new InvalidArgumentException("Keputusan [{$decision}] tidak valid.");
        }

        if ($decision === 'rejected' && empty($reason)) {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
        }

        $communities = Community::whereIn('id', $ids)->get();

        if ($communities->count() !== count(array_unique($ids))) {
            throw new DomainException('Data komunitas tidak ditemukan atau tidak lengkap.');
        }

        if (! $this->isBypassed($user)) {
            $scopedCityIds = $this->getScopedCityIds($user) ?? [];
            foreach ($communities as $community) {
                if ($community->city_id === null || ! in_array($community->city_id, $scopedCityIds, true)) {
                    throw new DomainException('Terdapat komunitas di luar wilayah pengawasan. Seluruh batch ditolak.');
                }
            }
        }

        return DB::transaction(function () use ($communities, $decision, $user, $reason): int {
            $now = now();
            foreach ($communities as $community) {
                $community->status = $decision;
                $community->verified_at = $now;
                $community->verified_by = $user->id;
                $community->reject_reason = $decision === 'rejected' ? $reason : null;
                $community->save();
            }

            return $communities->count();
        });
    }

    /**
     * Overseer assignments CRUD.
     */
    public function assignOverseer(int $userId, string $cityId): RegionOverseer
    {
        return RegionOverseer::firstOrCreate([
            'user_id' => $userId,
            'city_id' => $cityId,
        ]);
    }

    public function removeOverseer(int $id): bool
    {
        $overseer = RegionOverseer::findOrFail($id);

        return (bool) $overseer->delete();
    }
}
