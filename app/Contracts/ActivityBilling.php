<?php

declare(strict_types=1);

namespace App\Contracts;

interface ActivityBilling
{
    public function invoice(int $userId, int $registrationId, int $amount): array;

    public function find(int $registrationId): ?array;

    public function paid(int $registrationId, int $amount): bool;

    public function confirm(int $registrationId, int $amount): void;

    public function recordManualProof(int $registrationId, array $proof): void;
}
