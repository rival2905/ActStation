<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityLinkPage;
use App\Modules\Community\Services\CommunityLinkAnalytics;
use App\Modules\Community\Services\CommunityLinkPageService;

class CommunityLinkPublicController extends Controller
{
    private function published(string $handle): array
    {
        $page = CommunityLinkPage::where('handle', $handle)->where('status', 'published')->whereNull('suspended_at')->firstOrFail();
        $community = Community::whereKey($page->community_id)->where('is_active', true)->firstOrFail();

        return [$page, $community];
    }

    public function show(string $handle, CommunityLinkPageService $service, CommunityLinkAnalytics $analytics)
    {
        [$page, $community] = $this->published($handle);
        $content = $page->published_content;
        abort_unless(is_array($content), 404);
        $themes = $service->themes($community);
        if (! isset($themes[$content['theme_id']])) {
            $content['theme_id'] = 'clean-light';
        }
        try {
            $analytics->record($page);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->view('community.link_page', compact('page', 'community', 'content', 'themes') + ['preview' => false])->header('Cache-Control', 'no-store, private');
    }

    public function click(string $handle, string $linkId, CommunityLinkAnalytics $analytics)
    {
        [$page] = $this->published($handle);
        $link = collect($page->published_content['links'] ?? [])->firstWhere('id', $linkId);
        abort_unless($link && ! empty($link['visible']) && CommunityLinkPageService::safeUrl($link['url']), 404);
        try {
            $analytics->record($page, $linkId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->away($link['url'])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }
}
