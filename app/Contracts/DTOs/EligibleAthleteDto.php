<?php

declare(strict_types=1);

namespace App\Contracts\DTOs;

use Carbon\CarbonInterface;
use JsonSerializable;
use LogicException;

final readonly class EligibleAthleteDto implements JsonSerializable
{
    // ponytail: birthDate kept in DTO strictly for age validation; upgrade to sanitized view models if serialized.
    public function __construct(
        public int $userId,
        public string $name,
        public ?CarbonInterface $birthDate,
        public ?string $gender,
        public ?int $developmentLevelId,
    ) {}

    public function jsonSerialize(): mixed
    {
        throw new LogicException('EligibleAthleteDto contains birthDate and must not be serialized to response.');
    }
}
