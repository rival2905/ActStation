<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityLinkPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunityLinkModerationService
{
    public function listing()
    {
        return CommunityLinkPage::latest()->paginate(20);
    }

    public function themeAccessListing(?string $q): array
    {
        $communities = Community::query()->select(['id', 'name', 'is_active'])
            ->when($q !== null && $q !== '', function ($query) use ($q): void {
                $query->where(function ($query) use ($q): void {
                    $query->where('name', 'like', '%'.$q.'%');
                    if (ctype_digit($q)) {
                        $query->orWhere('id', $q);
                    }
                });
            })->orderBy('name')->paginate(20)->withQueryString();
        $keys = $communities->map(fn ($community) => 'community_links.premium.'.$community->id)->all();
        $overrides = DB::table('settings')->where('group', 'community_links')->whereIn('key', $keys)->pluck('value', 'key');
        $legacy = array_map('intval', config('community_links.exclusive_communities', []));
        $access = [];
        foreach ($communities as $community) {
            $key = 'community_links.premium.'.$community->id;
            $access[$community->id] = $overrides->has($key) ? $overrides[$key] === '1' : in_array($community->id, $legacy, true);
        }

        return compact('communities', 'access', 'q');
    }

    public function setThemeAccess(Community $community, User $actor, bool $enabled): void
    {
        DB::transaction(function () use ($community, $actor, $enabled): void {
            abort_unless($actor->fresh()?->is_active && $actor->can('community-links.moderate'), 403);
            $community = Community::whereKey($community->id)->lockForUpdate()->firstOrFail();
            if ($enabled && ! $community->is_active) {
                throw ValidationException::withMessages(['enabled' => 'Aktifkan komunitas sebelum memberi akses tema premium.']);
            }
            $previous = app(CommunityLinkPageService::class)->hasPremiumAccess($community);
            DB::table('settings')->updateOrInsert(
                ['group' => 'community_links', 'key' => 'community_links.premium.'.$community->id],
                ['value' => $enabled ? '1' : '0', 'type' => 'boolean', 'updated_at' => now()]
            );
            if ($previous !== $enabled) {
                ActivityLog::create(['user_id' => $actor->id, 'event' => 'updated', 'auditable_type' => Community::class, 'auditable_id' => $community->id, 'old_values' => ['premium' => $previous], 'new_values' => ['action' => 'premium_theme_access', 'theme_id' => 'perinatologi-care', 'premium' => $enabled]]);
            }
        });
    }

    public function decide(CommunityLinkPage $page, User $actor, array $data, bool $restore): void
    {
        DB::transaction(function () use ($page, $actor, $data, $restore): void {
            abort_unless($actor->fresh()?->is_active && $actor->can('community-links.moderate'), 403);
            Community::withTrashed()->whereKey($page->community_id)->lockForUpdate()->firstOrFail();
            $page = CommunityLinkPage::whereKey($page->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $page->revision === (int) $data['revision'], 409, 'Halaman berubah. Muat ulang.');
            $page->suspended_at = $restore ? null : now();
            $page->suspended_by = $restore ? null : $actor->id;
            $page->suspension_reason = $data['reason'];
            if ($restore) {
                $page->status = 'unpublished';
            }
            $page->revision++;
            $page->save();
            ActivityLog::create(['user_id' => $actor->id, 'event' => 'updated', 'auditable_type' => CommunityLinkPage::class, 'auditable_id' => $page->id, 'new_values' => ['action' => $restore ? 'restore' : 'suspend', 'revision' => $page->revision, 'reason' => $data['reason']]]);
        });
    }
}
