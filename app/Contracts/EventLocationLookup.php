<?php

declare(strict_types=1);

namespace App\Contracts;

interface EventLocationLookup
{
    /** @return array{venues: array, courts: array} */
    public function options(int $eventId): array;

    public function game(int $eventId, int $gameId): ?array;
}
