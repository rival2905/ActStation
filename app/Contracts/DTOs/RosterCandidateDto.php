<?php

declare(strict_types=1);

namespace App\Contracts\DTOs;

use JsonSerializable;

final readonly class RosterCandidateDto implements JsonSerializable
{
    public function __construct(
        public int $userId,
        public string $name,
        public string $membershipStatus,
        public string $regionStatus,
        public bool $meetsMembershipRequirements,
        public ?string $reason,
        public ?string $gender = null,
        public ?string $birthDate = null,
        public ?int $age = null,
        public ?string $studentIdentifierType = null,
        public ?string $studentIdentifierValue = null,
        public ?string $approvalUrl = null,
    ) {}

    /** @return array<string, int|string|bool|null> */
    public function jsonSerialize(): array
    {
        return [
            'user_id' => $this->userId,
            'name' => $this->name,
            'membership_status' => $this->membershipStatus,
            'region_status' => $this->regionStatus,
            'meets_membership_requirements' => $this->meetsMembershipRequirements,
            'reason' => $this->reason,
            'gender' => $this->gender,
            'birth_date' => $this->birthDate,
            'age' => $this->age,
            'student_identifier_type' => $this->studentIdentifierType,
            'student_identifier_value' => $this->studentIdentifierValue,
            'approval_url' => $this->approvalUrl,
        ];
    }
}
