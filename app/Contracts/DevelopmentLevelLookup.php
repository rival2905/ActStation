<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\DTOs\DevelopmentLevelSummary;
use Carbon\CarbonInterface;

interface DevelopmentLevelLookup
{
    public function findForBirthDate(CarbonInterface $birthDate, ?int $sportCategoryId = null): ?DevelopmentLevelSummary;
}
