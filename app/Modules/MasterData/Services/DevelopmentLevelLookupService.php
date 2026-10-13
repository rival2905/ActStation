<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Services;

use App\Contracts\DevelopmentLevelLookup;
use App\Contracts\DTOs\DevelopmentLevelSummary;
use App\Modules\MasterData\Models\DevelopmentLevel;
use Carbon\CarbonInterface;

class DevelopmentLevelLookupService implements DevelopmentLevelLookup
{
    public function findForBirthDate(CarbonInterface $birthDate, ?int $sportCategoryId = null): ?DevelopmentLevelSummary
    {
        $level = DevelopmentLevel::forBirthDate($birthDate, $sportCategoryId);

        if (! $level) {
            return null;
        }

        return new DevelopmentLevelSummary(
            id: (int) $level->id,
            name: (string) $level->name,
            minAge: $level->min_age !== null ? (int) $level->min_age : null,
            maxAge: $level->max_age !== null ? (int) $level->max_age : null,
        );
    }
}
