<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

interface ContextualAthleteInvitation
{
    /**
     * @return array{plain_token: string}
     */
    public function createForDraft(
        int $communityId,
        string $draftId,
        int $eventCategoryId,
        int $maxRegistrations,
        User $creator,
    ): array;

    public function invalidateForDraft(string $draftId): void;
}
