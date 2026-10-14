<?php

declare(strict_types=1);

namespace App\Contracts\DTOs;

final readonly class CommunitySummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $status,
        public string $subtype,
        public ?int $sport_category_id,
        public ?string $city_id,
        public ?string $institution_type = null,
        public bool $is_active = true,
        public ?string $school_level = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}
}
