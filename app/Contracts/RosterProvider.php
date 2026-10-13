<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\DTOs\EligibleAthleteDto;
use Illuminate\Support\Collection;

interface RosterProvider
{
    /**
     * @return Collection<int, EligibleAthleteDto>
     */
    public function eligibleAthletes(int $communityId, int $categoryId): Collection;
}
