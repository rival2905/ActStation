<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityCoach;
use App\Modules\Community\Models\CommunityOfficial;

class MemberCommunityDirectory
{
    public function show(User $user, Community $community): array
    {
        abort_unless($user->is_active && $community->is_active, 403);
        $isAthlete = $community->athletes()->where('user_id', $user->id)->where('status', 'active')->whereNull('left_at')->exists();
        $isOfficial = $community->officials()->where('user_id', $user->id)->where('status', 'active')->whereNull('ended_at')->exists();
        abort_unless($isAthlete || $isOfficial, 403);

        $groups = [];
        foreach (['athletes' => CommunityAthlete::class, 'officials' => CommunityOfficial::class, 'coaches' => CommunityCoach::class] as $key => $model) {
            $athlete = $key === 'athletes';
            $start = $athlete ? 'joined_at' : 'started_at';
            $end = $athlete ? 'left_at' : 'ended_at';
            $columns = ['id', 'user_id', 'status', $start, $end];
            if ($key === 'officials') {
                $columns[] = 'position';
            }
            $groups[$key] = $model::query()->where('community_id', $community->id)
                ->whereIn('status', ['active', $athlete ? 'left' : 'inactive'])
                ->select($columns)->with('user:id,name')->orderByDesc($start)->orderByDesc('id')
                ->paginate(20, ['*'], $key.'_page')->withQueryString()
                ->through(fn ($member): array => [
                    'name' => $member->user?->name ?? 'Akun tidak tersedia',
                    'role' => $key === 'officials' ? $member->position : ($athlete ? 'Atlet' : 'Pelatih'),
                    'current' => $member->status === 'active' && $member->{$end} === null,
                    'started_at' => $member->{$start}?->format('d-m-Y'),
                    'ended_at' => $member->{$end}?->format('d-m-Y'),
                ]);
        }

        return ['name' => $community->name, 'groups' => $groups];
    }
}
