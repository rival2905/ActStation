<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\DTOs\CommunitySummary;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface CommunityLookup
{
    public function find(int $id): ?CommunitySummary;

    public function findForUser(int $userId): ?CommunitySummary;

    /** @return list<CommunitySummary> */
    public function athleteCommunities(int $userId): array;

    /** @return list<CommunitySummary> */
    public function searchableCommunities(string $query): array;

    public function canManage(int $userId, int $communityId): bool;

    /** @return list<int> */
    public function activeAthleteIds(int $communityId): array;

    /**
     * @return array<string, array{id: int, category: string, name: string}>
     */
    public function legalDocumentsForUser(int $userId, int $communityId): array;

    public function legalDocumentDownloadForUser(int $userId, int $communityId, string $code): StreamedResponse;
}
