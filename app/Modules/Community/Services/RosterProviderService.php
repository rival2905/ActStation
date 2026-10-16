<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\DTOs\EligibleAthleteDto;
use App\Contracts\DTOs\RosterCandidateDto;
use App\Contracts\RosterProvider;
use App\Modules\Community\Models\CommunityAthlete;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RosterProviderService implements RosterProvider
{
    /**
     * @return Builder<CommunityAthlete>
     */
    public function eligibleAthletesQuery(int $communityId, int $categoryId): Builder
    {
        return CommunityAthlete::query()
            ->with(['user', 'community'])
            ->where('community_id', $communityId)
            ->where('status', 'active')
            ->whereNull('left_at')
            ->whereHas('user', fn ($query) => $query->where('is_active', true));
    }

    /**
     * @return Collection<int, EligibleAthleteDto>
     */
    public function eligibleAthletes(int $communityId, int $categoryId): Collection
    {
        // ponytail: categoryId filtering by gender/development-level delegated to validation service or rules; query requires active membership, not regional verification.
        return $this->eligibleAthletesQuery($communityId, $categoryId)
            ->get()
            ->filter(fn (CommunityAthlete $athlete): bool => $athlete->user !== null)
            ->map(fn (CommunityAthlete $athlete): EligibleAthleteDto => new EligibleAthleteDto(
                userId: (int) $athlete->user_id,
                name: (string) $athlete->user->name,
                birthDate: $athlete->user->birth_date,
                gender: $athlete->user->gender !== null ? (string) $athlete->user->gender : null,
                developmentLevelId: $athlete->development_level_id !== null ? (int) $athlete->development_level_id : null,
                studentIdentifierType: $this->studentIdentityType($athlete->community->subtype),
                studentIdentifierValue: match ($this->studentIdentityType($athlete->community->subtype)) {
                    'nisn' => $athlete->nisn, 'nim' => $athlete->nim, default => null
                },
            ))
            ->values();
    }

    /**
     * @return Collection<int, RosterCandidateDto>
     */
    public function candidatesForDraft(string $draftId, int $communityId): Collection
    {
        $memberships = CommunityAthlete::query()->where('community_id', $communityId)->whereNull('left_at')
            ->whereIn('user_id', DB::table('registration_draft_candidates')->where('registration_draft_id', $draftId)->select('user_id'))
            ->with('community')->get()->keyBy('user_id');

        return DB::table('registration_draft_candidates as candidates')
            ->join('users', 'users.id', '=', 'candidates.user_id')
            ->join('community_athletes as memberships', function ($join) use ($communityId): void {
                $join->on('memberships.user_id', '=', 'candidates.user_id')
                    ->where('memberships.community_id', '=', $communityId)
                    ->whereNull('memberships.left_at');
            })
            ->where('candidates.registration_draft_id', $draftId)
            ->where('users.is_active', true)
            ->orderBy('candidates.id')
            ->get([
                'users.id as user_id',
                'users.name',
                'users.gender',
                'users.birth_date',
                'memberships.status as membership_status',
                'memberships.region_status',
            ])
            ->map(function (object $row) use ($memberships): RosterCandidateDto {
                $membership = $memberships->get($row->user_id);
                $identityType = $membership ? $this->studentIdentityType($membership->community->subtype) : null;
                $membershipReady = $row->membership_status === 'active';
                $age = $row->birth_date ? Carbon::parse($row->birth_date)->age : null;

                return new RosterCandidateDto(
                    userId: (int) $row->user_id,
                    name: (string) $row->name,
                    membershipStatus: (string) $row->membership_status,
                    regionStatus: (string) $row->region_status,
                    meetsMembershipRequirements: $membershipReady,
                    reason: ! $membershipReady ? 'Keanggotaan belum disetujui pengurus.' : null,
                    gender: $row->gender ? (string) $row->gender : null,
                    birthDate: $row->birth_date ? (string) $row->birth_date : null,
                    age: $age,
                    studentIdentifierType: $identityType,
                    studentIdentifierValue: match ($identityType) {
                        'nisn' => $membership->nisn, 'nim' => $membership->nim, default => null
                    },
                );
            })
            ->values();
    }

    private function studentIdentityType(string $subtype): ?string
    {
        return match ($subtype) {
            'school', 'school_sd', 'school_smp', 'school_sma' => 'nisn',
            'university' => 'nim',
            default => null,
        };
    }

    public function isCandidateForDraft(string $draftId, int $communityId, int $userId): bool
    {
        return DB::table('registration_draft_candidates as candidates')
            ->join('community_athletes as memberships', function ($join) use ($communityId): void {
                $join->on('memberships.user_id', '=', 'candidates.user_id')
                    ->where('memberships.community_id', '=', $communityId)
                    ->whereNull('memberships.left_at');
            })
            ->where('candidates.registration_draft_id', $draftId)
            ->where('candidates.user_id', $userId)
            ->exists();
    }
}
