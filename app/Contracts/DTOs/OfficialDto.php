<?php

declare(strict_types=1);

namespace App\Contracts\DTOs;

final readonly class OfficialDto
{
    public function __construct(
        public int $userId,
        public string $name,
    ) {}
}
