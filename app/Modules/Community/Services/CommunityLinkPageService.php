<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityLinkPage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CommunityLinkPageService
{
    public function themes(Community $community): array
    {
        $themes = [
            'clean-light' => ['name' => 'Clean Light', 'background' => '#f8fafc', 'color' => '#0f172a', 'accent' => '#2563eb'],
            'midnight' => ['name' => 'Midnight', 'background' => '#0f172a', 'color' => '#f8fafc', 'accent' => '#38bdf8'],
            'sport-bold' => ['name' => 'Sport Bold', 'background' => '#fef08a', 'color' => '#171717', 'accent' => '#171717'],
            'soft-pastel' => ['name' => 'Soft Pastel', 'background' => '#fce7f3', 'color' => '#4a044e', 'accent' => '#86198f'],
            'gradient' => ['name' => 'Gradient', 'background' => '#e0e7ff', 'color' => '#312e81', 'accent' => '#4338ca'],
            'photo' => ['name' => 'Photo', 'background' => '#d6d3d1', 'color' => '#292524', 'accent' => '#44403c'],
        ];
        foreach ($themes as &$theme) {
            $theme['exclusive'] = false;
        }
        unset($theme);
        if ($this->hasPremiumAccess($community)) {
            $themes['perinatologi-care'] = ['name' => 'Perinatologi Care', 'background' => '#f0fdfa', 'color' => '#134e4a', 'accent' => '#0f766e', 'exclusive' => true];
        }

        return $themes;
    }

    public function hasPremiumAccess(Community $community): bool
    {
        $value = DB::table('settings')->where('group', 'community_links')->where('key', 'community_links.premium.'.$community->id)->value('value');

        return $value === null
            ? in_array($community->id, array_map('intval', config('community_links.exclusive_communities', [])), true)
            : $value === '1';
    }

    private function authorize(Community $community, User $actor): Community
    {
        $community = Community::whereKey($community->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
        abort_unless($actor->fresh()?->is_active, 403);
        Gate::forUser($actor)->authorize('update', $community);

        return $community;
    }

    private function checkTheme(Community $community, string $theme): void
    {
        if (! isset($this->themes($community)[$theme])) {
            throw ValidationException::withMessages(['theme_id' => 'Tema tidak tersedia untuk komunitas ini.']);
        }
    }

    public function create(Community $community, User $actor, array $data): CommunityLinkPage
    {
        try {
            return DB::transaction(function () use ($community, $actor, $data) {
                $community = $this->authorize($community, $actor);
                $this->checkTheme($community, $data['theme_id']);
                $count = CommunityLinkPage::where('community_id', $community->id)->count();
                if ($count >= 20) {
                    throw ValidationException::withMessages(['handle' => 'Maksimal 20 halaman per komunitas.']);
                }
                $page = CommunityLinkPage::create([
                    'community_id' => $community->id, 'handle' => $data['handle'], 'is_primary' => $count === 0,
                    'status' => 'draft', 'revision' => 1,
                    'draft_content' => ['title' => $data['title'], 'bio' => '', 'theme_id' => $data['theme_id'], 'categories' => [], 'links' => [], 'socials' => []],
                ]);
                $this->audit($page, $actor, 'created');

                return $page;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['handle' => 'Alamat halaman sudah digunakan.']);
        }
    }

    public function mutate(Community $community, CommunityLinkPage $page, User $actor, string $action, array $data): CommunityLinkPage
    {
        return DB::transaction(function () use ($community, $page, $actor, $action, $data) {
            $community = $this->authorize($community, $actor);
            $page = CommunityLinkPage::whereKey($page->id)->where('community_id', $community->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $data['revision'] === (int) $page->revision, 409, 'Halaman telah berubah. Muat ulang sebelum menyimpan.');
            if ($action === 'save') {
                $this->checkTheme($community, $data['theme_id']);
                $content = array_intersect_key($data, array_flip(['title', 'bio', 'theme_id', 'categories', 'links', 'socials']));
                $content += ['bio' => '', 'categories' => [], 'links' => [], 'socials' => []];
                if (isset($page->draft_content['avatar'])) {
                    $content['avatar'] = $page->draft_content['avatar'];
                }
                if (isset($page->draft_content['background'])) {
                    $content['background'] = $page->draft_content['background'];
                }
                $page->draft_content = $content;
            } elseif ($action === 'publish') {
                abort_if($page->suspended_at !== null, 403, 'Halaman sedang ditangguhkan.');
                $content = $page->draft_content;
                $this->checkTheme($community, $content['theme_id']);
                $categories = collect($content['categories'] ?? [])->filter(fn ($category) => ($category['visible'] ?? true))->values();
                $content['links'] = collect($content['links'] ?? [])->filter(fn ($link) => ! empty($link['visible']) && (empty($link['category_id']) || $categories->contains('id', $link['category_id'])))->values()->all();
                $content['categories'] = $categories->filter(fn ($category) => collect($content['links'])->contains('category_id', $category['id']))->values()->all();
                if (empty($content['links']) && empty($content['socials'])) {
                    throw ValidationException::withMessages(['links' => 'Tambahkan minimal satu tautan terlihat sebelum terbit.']);
                }
                foreach (array_merge($content['links'], $content['socials'] ?? []) as $link) {
                    if (! self::safeUrl($link['url'])) {
                        throw ValidationException::withMessages(['links' => 'Tautan publik tidak valid.']);
                    }
                }
                $page->published_content = $content;
                $page->status = 'published';
                $page->published_at = now();
            } elseif ($action === 'remove-avatar') {
                $content = $page->draft_content;
                unset($content['avatar']);
                $page->draft_content = $content;
            } elseif ($action === 'unpublish') {
                $page->status = 'unpublished';
            } elseif ($action === 'primary') {
                CommunityLinkPage::where('community_id', $community->id)->whereKeyNot($page->id)->update(['is_primary' => false]);
                $page->is_primary = true;
            } else {
                abort(404);
            }
            $page->revision++;
            $page->save();
            $this->audit($page, $actor, $action);

            return $page;
        });
    }

    private function audit(CommunityLinkPage $page, User $actor, string $action): void
    {
        ActivityLog::create(['user_id' => $actor->id, 'event' => $action === 'created' ? 'created' : 'updated', 'auditable_type' => CommunityLinkPage::class, 'auditable_id' => $page->id, 'new_values' => ['community_id' => $page->community_id, 'revision' => $page->revision, 'action' => $action]]);
    }

    public static function safeUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);

        return in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ! empty($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }
}
