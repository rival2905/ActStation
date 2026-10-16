<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\CommunityLookup;
use App\Contracts\RosterProvider;
use App\Modules\Event\Services\RegistrationInspectionSummary;
use App\Modules\Event\Services\RosterValidator;
use PHPUnit\Framework\TestCase;

class RegistrationInspectionSummaryTest extends TestCase
{
    public function test_service_initialization_and_signature(): void
    {
        $rosterProvider = $this->createMock(RosterProvider::class);
        $communityLookup = $this->createMock(CommunityLookup::class);
        $rosterValidator = $this->createMock(RosterValidator::class);

        $service = new RegistrationInspectionSummary(
            $rosterProvider,
            $communityLookup,
            $rosterValidator
        );

        $this->assertInstanceOf(RegistrationInspectionSummary::class, $service);
    }
}
