<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\DTOs\EventSummary;

interface EventLookup
{
    public function find(int $id): ?EventSummary;
}
