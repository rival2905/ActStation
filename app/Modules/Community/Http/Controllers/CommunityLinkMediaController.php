<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers;

use App\Modules\Community\Http\Controllers\Panel\PanelBaseController;
use App\Modules\Community\Http\Requests\CommunityLinkMediaRequest;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityLinkPage;
use App\Modules\Community\Services\CommunityLinkMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CommunityLinkMediaController extends PanelBaseController
{
    public function upload(CommunityLinkMediaRequest $request, CommunityLinkPage $page, CommunityLinkMedia $media)
    {
        $media->upload($this->currentCommunity($request), $page, $request->user(), $request->file('avatar'), (int) $request->validated('revision'), $request->validated('asset_kind', 'avatar'));

        return redirect()->route('panel.community-links.edit', $page)->with('success', 'Avatar draft tersimpan; terbitkan untuk memperbarui publik.');
    }

    private function file(CommunityLinkPage $page, string $asset)
    {
        abort_unless(Str::isUuid($asset), 404);
        $path = 'community-links/'.$page->id.'/'.$asset.'.webp';
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path))->header('Content-Type', 'image/webp')->header('Cache-Control', 'no-store, private')->header('X-Content-Type-Options', 'nosniff');
    }

    public function avatar(string $handle, string $asset)
    {
        $page = CommunityLinkPage::where('handle', $handle)->where('status', 'published')->whereNull('suspended_at')->firstOrFail();
        Community::whereKey($page->community_id)->where('is_active', true)->firstOrFail();
        abort_unless(in_array($asset, [$page->published_content['avatar'] ?? null, $page->published_content['background'] ?? null], true), 404);

        return $this->file($page, $asset);
    }

    public function preview(Request $request, CommunityLinkPage $page, string $asset)
    {
        $community = $this->currentCommunity($request);
        abort_unless($request->user()->is_active, 403);
        $this->authorize('update', $community);
        abort_unless($page->community_id === $community->id && in_array($asset, [$page->draft_content['avatar'] ?? null, $page->published_content['avatar'] ?? null, $page->draft_content['background'] ?? null, $page->published_content['background'] ?? null], true), 404);

        return $this->file($page, $asset);
    }
}
