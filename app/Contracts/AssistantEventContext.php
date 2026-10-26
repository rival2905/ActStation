<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

interface AssistantEventContext
{
    public function forUser(User $user, string $scope = 'all'): array;
}
