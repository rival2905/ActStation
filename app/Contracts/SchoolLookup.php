<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\DTOs\SchoolSummary;

interface SchoolLookup
{
    public function findByNpsn(string $npsn): ?SchoolSummary;

    /**
     * @return list<SchoolSummary>
     */
    public function search(string $query, ?string $level = null, ?string $cityId = null, int $limit = 20): array;

    /**
     * Derive canonical school subtype form token ('school_sd', 'school_smp', 'school_sma') from master school level.
     */
    public function deriveSubtypeToken(?string $schoolLevel): ?string;
}
