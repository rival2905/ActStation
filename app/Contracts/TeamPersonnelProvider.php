<?php

declare(strict_types=1);

namespace App\Contracts;

interface TeamPersonnelProvider
{
    public function roles(): array;

    public function eligiblePeople(int $communityId): array;

    public function requestMembership(int $communityId, int $userId, int $roleId): string;

    public function membershipStatus(int $communityId, int $userId, int $roleId): ?string;
}
