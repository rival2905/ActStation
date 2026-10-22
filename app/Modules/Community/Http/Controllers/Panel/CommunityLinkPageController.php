<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Modules\Community\Http\Requests\Panel\CommunityLinkPageRequest;
use App\Modules\Community\Models\CommunityLinkPage;
use App\Modules\Community\Services\CommunityLinkAnalytics;
use App\Modules\Community\Services\CommunityLinkPageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityLinkPageController extends PanelBaseController
{
    public function index(Request $request, CommunityLinkPageService $service)
    {
        $community = $this->currentCommunity($request);
        abort_unless($request->user()->is_active, 403);
        $this->authorize('update', $community);

        return view('panel.community_links.index', ['community' => $community, 'pages' => CommunityLinkPage::where('community_id', $community->id)->orderByDesc('is_primary')->latest()->get(), 'themes' => $service->themes($community)]);
    }

    public function store(CommunityLinkPageRequest $request, CommunityLinkPageService $service)
    {
        $page = $service->create($this->currentCommunity($request), $request->user(), $request->validated());

        return redirect()->route('panel.community-links.edit', $page)->with('success', 'Halaman diaktifkan sebagai draft, belum publik.');
    }

    public function edit(Request $request, CommunityLinkPage $page, CommunityLinkPageService $service, CommunityLinkAnalytics $analytics)
    {
        $community = $this->currentCommunity($request);
        abort_unless($request->user()->is_active, 403);
        $this->authorize('update', $community);
        abort_unless($page->community_id === $community->id, 404);

        return view('panel.community_links.edit', ['community' => $community, 'page' => $page, 'content' => $page->draft_content, 'themes' => $service->themes($community), 'analytics' => $analytics->summary($page)]);
    }

    public function update(CommunityLinkPageRequest $request, CommunityLinkPage $page, CommunityLinkPageService $service)
    {
        $page = $service->mutate($this->currentCommunity($request), $page, $request->user(), 'save', $request->validated());

        if ($request->expectsJson()) {
            return new JsonResource(['revision' => $page->revision, 'message' => 'Draft tersimpan. Publik belum berubah.']);
        }

        return redirect()->route('panel.community-links.edit', $page)->with('success', 'Draft tersimpan. Publik belum berubah.');
    }

    public function action(CommunityLinkPageRequest $request, CommunityLinkPage $page, CommunityLinkPageService $service)
    {
        $action = last(explode('.', $request->route()->getName()));
        $service->mutate($this->currentCommunity($request), $page, $request->user(), $action, $request->validated());

        return redirect()->route('panel.community-links.edit', $page)->with('success', $action === 'remove-avatar' ? 'Logo draft dihapus. Terbitkan untuk memperbarui halaman publik.' : 'Perubahan halaman disimpan.');
    }

    public function preview(Request $request, CommunityLinkPage $page, CommunityLinkPageService $service)
    {
        $community = $this->currentCommunity($request);
        abort_unless($request->user()->is_active, 403);
        $this->authorize('update', $community);
        abort_unless($page->community_id === $community->id, 404);
        $themes = $service->themes($community);
        $content = $page->draft_content;
        if (! isset($themes[$content['theme_id']])) {
            $content['theme_id'] = 'clean-light';
        }

        return response()->view('community.link_page', compact('community', 'page', 'content', 'themes') + ['preview' => true])->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
