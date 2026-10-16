<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\TeamPersonnelProvider;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityCoach;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SportsPersonnelRole;
use Illuminate\Support\Facades\DB;

class TeamPersonnelProviderService implements TeamPersonnelProvider
{
    public function roles(): array
    {
        return SportsPersonnelRole::orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'code', 'is_active'])->toArray();
    }

    public function membershipStatus(int $communityId, int $userId, int $roleId): ?string
    {
        $role = SportsPersonnelRole::whereKey($roleId)->where('is_active', true)->first();
        if (! $role) {
            return null;
        }
        $member = CommunityCoach::where('community_id', $communityId)->where('user_id', $userId)->whereNull('ended_at')
            ->where(function ($q) use ($role): void {
                $q->where('sports_personnel_role_id', $role->id);
                if ($role->code === 'coach') {
                    $q->orWhereNull('sports_personnel_role_id');
                }
            })->latest('id')->first();

        return $member?->status;
    }

    public function requestMembership(int $communityId, int $userId, int $roleId): string
    {
        return DB::transaction(function () use ($communityId, $userId, $roleId): string {
            Community::whereKey($communityId)->where('is_active', true)->lockForUpdate()->firstOrFail();
            User::whereKey($userId)->where('is_active', true)->lockForUpdate()->firstOrFail();
            SportsPersonnelRole::whereKey($roleId)->where('is_active', true)->lockForUpdate()->firstOrFail();
            $existing = $this->membershipStatus($communityId, $userId, $roleId);
            if ($existing !== null) {
                return $existing;
            }
            CommunityCoach::create(['community_id' => $communityId, 'user_id' => $userId, 'sports_personnel_role_id' => $roleId, 'status' => 'pending', 'gender_scope' => 'all', 'started_at' => today()]);

            return 'pending';
        });
    }

    public function eligiblePeople(int $communityId): array
    {
        $people = collect();
        foreach ([CommunityCoach::class => 'ended_at', CommunityOfficial::class => 'ended_at'] as $class => $end) {
            foreach ($class::where('community_id', $communityId)->where('status', 'active')->whereNull($end)->whereHas('user', fn ($q) => $q->where('is_active', true))->with('user:id,name')->get() as $member) {
                $old = $people->get($member->user_id);
                $people->put($member->user_id, ['user_id' => (int) $member->user_id, 'name' => $member->user->name, 'is_official' => ($old['is_official'] ?? false) || $member instanceof CommunityOfficial]);
            }
        }

        return $people->sortBy('name')->values()->all();
    }
}
