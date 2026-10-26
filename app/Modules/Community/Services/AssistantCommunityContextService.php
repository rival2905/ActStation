<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\AssistantCommunityContext;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;

class AssistantCommunityContextService implements AssistantCommunityContext
{
    public function forUser(User $user, string $scope = 'all'): array
    {
        abort_unless($user->is_active, 403);
        $result = [];
        if (in_array($scope, ['all', 'community'], true)) {
            $managed = Community::query()->where('is_active', true)->whereHas('officials', fn ($official) => $official
                ->where('user_id', $user->id)->where('status', 'active')->whereNull('ended_at'));
            if ($scope === 'community') {
                // Chat stays in the selected community; never enumerate every managed entity.
                $activeId = CommunityLookupService::resolveActiveCommunityId((int) $user->id);
                $managed->whereKey($activeId ?? 0);
            }
            $result = ['managed' => [
                'total' => (clone $managed)->count(),
                'items' => $managed->orderBy('id')->limit(5)->get(['id', 'name', 'status'])->map(fn (Community $community): array => [
                    'name' => $community->name, 'status' => $community->status,
                    'active_athletes' => $community->athletes()->where('status', 'active')->whereNull('left_at')->count(),
                    'pending_athletes' => $community->athletes()->where('status', 'pending')->whereNull('left_at')->count(),
                ])->all(),
            ]];
        }
        if (in_array($scope, ['all', 'personal'], true)) {
            $result['membership_requests'] = CommunityAthlete::query()->where('user_id', $user->id)->where('status', 'pending')
                ->whereNull('left_at')->whereHas('community', fn ($community) => $community->where('is_active', true))->count();
        }
        if (in_array($scope, ['all', 'oversight'], true) && $user->isInternal() && $user->can('region-oversight.view')) {
            $scoped = app(RegionOversightService::class)->applyCommunityScope(Community::query(), $user);
            $result['oversight'] = ['total' => (clone $scoped)->count(), 'by_status' => $scoped
                ->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status')->all()];
        }

        return $result;
    }
}
