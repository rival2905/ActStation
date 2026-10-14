<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\DTOs\CommunitySummary;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface CommunityLookup
{
    public function find(int $id): ?CommunitySummary;

    public function findForUser(int $userId): ?CommunitySummary;

    /**
     * @return array<string, array{id: int, category: string, name: string}>
     */
    public function legalDocumentsForUser(int $userId, int $communityId): array;

    public function legalDocumentDownloadForUser(int $userId, int $communityId, string $code): StreamedResponse;
}
