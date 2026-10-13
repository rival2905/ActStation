<?php

declare(strict_types=1);

namespace App\Contracts\DTOs;

use Carbon\CarbonInterface;

final readonly class EventSummary
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $status,
        public ?CarbonInterface $starts_at,
        public ?int $venue_id,
    ) {}
}
