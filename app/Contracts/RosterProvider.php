<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\DTOs\EligibleAthleteDto;
use App\Contracts\DTOs\RosterCandidateDto;
use Illuminate\Support\Collection;

interface RosterProvider
{
    /**
     * @return Collection<int, EligibleAthleteDto>
     */
    public function eligibleAthletes(int $communityId, int $categoryId): Collection;

    /**
     * @return Collection<int, RosterCandidateDto>
     */
    public function candidatesForDraft(string $draftId, int $communityId): Collection;

    public function isCandidateForDraft(string $draftId, int $communityId, int $userId): bool;
}
