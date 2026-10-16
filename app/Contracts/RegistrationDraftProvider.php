<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\DTOs\RegistrationDraftContextDto;

interface RegistrationDraftProvider
{
    public function validInvitationContext(
        string $draftId,
        int $communityId,
        int $eventCategoryId,
        bool $lockForUpdate = false,
    ): ?RegistrationDraftContextDto;

    public function addCandidate(string $draftId, int $userId, int $invitationId, array $rosterData = []): bool;

    public function containsAthlete(string $draftId, int $userId): bool;

    public function lockDraft(string $draftId): void;

    public function retainForInvitation(string $draftId): void;

    public function hasCandidateCapacity(string $draftId, ?int $userId = null): bool;
}
