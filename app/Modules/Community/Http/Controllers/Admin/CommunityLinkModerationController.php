<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\AdminCommunityLinkModerationRequest;
use App\Modules\Community\Http\Requests\AdminCommunityLinkThemeAccessRequest;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityLinkPage;
use App\Modules\Community\Services\CommunityLinkModerationService;

class CommunityLinkModerationController extends Controller
{
    public function index(CommunityLinkModerationService $service)
    {
        abort_unless(auth()->user()->is_active, 403);

        return view('admin.community_links.index', ['pages' => $service->listing()]);
    }

    public function decide(AdminCommunityLinkModerationRequest $request, CommunityLinkPage $page, CommunityLinkModerationService $service)
    {
        $service->decide($page, $request->user(), $request->validated(), $request->routeIs('admin.community-links.restore'));

        return redirect()->route('admin.community-links.index')->with('success', 'Keputusan moderasi disimpan. Pemulihan tidak menerbitkan ulang halaman.');
    }

    public function themeAccess(AdminCommunityLinkThemeAccessRequest $request, CommunityLinkModerationService $service)
    {
        return view('admin.community_links.theme_access', $service->themeAccessListing($request->validated('q')));
    }

    public function updateThemeAccess(AdminCommunityLinkThemeAccessRequest $request, Community $community, CommunityLinkModerationService $service)
    {
        $service->setThemeAccess($community, $request->user(), $request->boolean('enabled'));

        return redirect()->route('admin.community-links.theme-access')->with('success', 'Akses tema premium komunitas diperbarui. Tidak ada halaman yang otomatis diterbitkan.');
    }
}
