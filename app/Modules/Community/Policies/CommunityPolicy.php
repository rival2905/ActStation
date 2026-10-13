<?php

declare(strict_types=1);

namespace App\Modules\Community\Policies;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;

class CommunityPolicy
{
    public function view(User $user, Community $community): bool
    {
        if ($user->can('community.view')) {
            return true;
        }

        return $this->isActiveOfficial($user, $community);
    }

    public function update(User $user, Community $community): bool
    {
        if ($user->can('community.update')) {
            return true;
        }

        return $this->isActiveOfficial($user, $community);
    }

    public function manage(User $user, Community $community): bool
    {
        return $this->update($user, $community);
    }

    public function isActiveOfficial(User $user, Community $community): bool
    {
        return CommunityOfficial::query()
            ->where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->exists();
    }
}
