<?php

declare(strict_types=1);

namespace App\Contracts;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface TeamDocumentInspection
{
    /** @return array<string, mixed>|null */
    public function find(string $ownerType, int $ownerId, string $code): ?array;

    public function download(string $ownerType, int $ownerId, string $code): StreamedResponse;

    public function review(string $ownerType, int $ownerId, string $code, string $version, string $status, ?string $notes, int $reviewerId): void;
}
