<?php

declare(strict_types=1);

namespace App\Contracts\DTOs;

final readonly class SchoolSummary
{
    public function __construct(
        public int $id,
        public string $npsn,
        public string $name,
        public string $school_level,
        public ?string $school_status = null,
        public ?string $province_id = null,
        public ?string $city_id = null,
        public ?string $address = null,
        public bool $is_registered = false,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}
}
