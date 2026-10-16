<?php

declare(strict_types=1);

namespace App\Contracts\DTOs;

final readonly class RegistrationDraftContextDto
{
    public function __construct(
        public string $draftId,
        public int $communityId,
        public int $eventId,
        public int $eventCategoryId,
        public int $revision,
        public int $maxRoster,
        public ?string $communitySubtype = null,
        public string $gender = 'mixed',
        public ?int $minAge = null,
        public ?int $maxAge = null,
        public ?string $eventStartsAt = null,
        public ?int $developmentLevelId = null,
        public ?int $sportCategoryId = null,
        public ?string $sportSlug = null,
    ) {}
}
