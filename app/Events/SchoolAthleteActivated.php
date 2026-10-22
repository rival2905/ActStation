<?php

declare(strict_types=1);

namespace App\Events;

final readonly class SchoolAthleteActivated
{
    public function __construct(public int $communityId, public int $userId) {}
}
