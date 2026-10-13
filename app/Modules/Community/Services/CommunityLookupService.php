<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\CommunityLookup;
use App\Contracts\DTOs\CommunitySummary;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;

class CommunityLookupService implements CommunityLookup
{
    public function find(int $id): ?CommunitySummary
    {
        $community = Community::find($id);

        if (! $community) {
            return null;
        }

        return new CommunitySummary(
            id: (int) $community->id,
            name: (string) $community->name,
            slug: (string) $community->slug,
            status: (string) $community->status,
            subtype: (string) $community->subtype,
            sport_category_id: $community->sport_category_id !== null ? (int) $community->sport_category_id : null,
            city_id: $community->city_id !== null ? (string) $community->city_id : null,
        );
    }

    public function findForUser(int $userId): ?CommunitySummary
    {
        $official = CommunityOfficial::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->first();

        if (! $official) {
            return null;
        }

        return $this->find((int) $official->community_id);
    }
}
