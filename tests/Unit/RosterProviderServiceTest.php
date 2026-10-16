<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Community\Services\RosterProviderService;
use Tests\TestCase;

uses(TestCase::class);

test('eligibleAthletes query excludes inactive users and departed members', function (): void {
    $service = new RosterProviderService;
    $query = $service->eligibleAthletesQuery(42, 99);

    $sql = strtolower($query->toSql());

    expect($sql)
        ->toContain('left_at')
        ->toContain('is null')
        ->toContain('is_active');
});
