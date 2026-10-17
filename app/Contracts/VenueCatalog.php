<?php

declare(strict_types=1);

namespace App\Contracts;

interface VenueCatalog
{
    public function allowsPanelCreation(bool $court): bool;

    public function createVenue(array $data): int;

    public function createCourt(int $venueId, array $data): int;
}
