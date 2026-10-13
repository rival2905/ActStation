<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\DTOs\CommunitySummary;

interface CommunityLookup
{
    public function find(int $id): ?CommunitySummary;

    public function findForUser(int $userId): ?CommunitySummary;
}
