<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\DTOs\EligibleAthleteDto;
use App\Contracts\RosterProvider;
use App\Modules\Community\Models\CommunityAthlete;
use Illuminate\Support\Collection;

class RosterProviderService implements RosterProvider
{
    /**
     * @return Collection<int, EligibleAthleteDto>
     */
    public function eligibleAthletes(int $communityId, int $categoryId): Collection
    {
        // ponytail: categoryId filtering by gender/development-level delegated to validation service or rules; query satisfies verified active roster.
        return CommunityAthlete::query()
            ->with('user')
            ->where('community_id', $communityId)
            ->where('status', 'active')
            ->where('region_status', 'verified')
            ->get()
            ->filter(fn (CommunityAthlete $athlete): bool => $athlete->user !== null)
            ->map(fn (CommunityAthlete $athlete): EligibleAthleteDto => new EligibleAthleteDto(
                userId: (int) $athlete->user_id,
                name: (string) $athlete->user->name,
                birthDate: $athlete->user->birth_date,
                gender: $athlete->user->gender !== null ? (string) $athlete->user->gender : null,
                developmentLevelId: $athlete->development_level_id !== null ? (int) $athlete->development_level_id : null,
            ))
            ->values();
    }
}
