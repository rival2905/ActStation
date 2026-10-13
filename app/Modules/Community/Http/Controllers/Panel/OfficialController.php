<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Modules\Community\Http\Requests\Panel\PanelOfficialRequest;
use App\Modules\Community\Models\CommunityOfficial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfficialController extends PanelBaseController
{
    public function index(Request $request): View
    {
        $community = $this->currentCommunity($request);
        $this->authorize('view', $community);

        $officials = $community->officials()->with('user')->latest()->get();

        return view('panel.officials.index', compact('community', 'officials'));
    }

    public function store(PanelOfficialRequest $request): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        $data = $request->validated();
        $data['started_at'] = $data['started_at'] ?? now()->toDateString();
        $data['status'] = $data['status'] ?? 'active';

        $community->officials()->create($data);

        return redirect()
            ->route('panel.officials.index')
            ->with('success', 'Pengurus berhasil ditambahkan.');
    }

    public function destroy(Request $request, CommunityOfficial $official): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);
        $this->assertBelongsToCommunity($community, $official->community_id);

        $official->delete();

        return redirect()
            ->route('panel.officials.index')
            ->with('success', 'Pengurus berhasil dihapus.');
    }
}
