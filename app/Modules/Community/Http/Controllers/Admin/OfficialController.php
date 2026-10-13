<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\OfficialRequest;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OfficialController extends Controller
{
    public function index(Community $community): View
    {
        $officials = $community->officials()->with('user')->latest()->get();

        return view('admin.communities.officials.index', compact('community', 'officials'));
    }

    public function store(OfficialRequest $request, Community $community): RedirectResponse
    {
        $community->officials()->create($request->validated());

        return redirect()
            ->route('admin.communities.officials.index', $community)
            ->with('success', 'Pengurus berhasil ditambahkan.');
    }

    public function destroy(Community $community, CommunityOfficial $official): RedirectResponse
    {
        abort_if($official->community_id !== $community->id, 404);

        $official->delete();

        return redirect()
            ->route('admin.communities.officials.index', $community)
            ->with('success', 'Pengurus berhasil dihapus.');
    }
}
