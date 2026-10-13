<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Modules\Community\Http\Requests\Panel\PanelCoachRequest;
use App\Modules\Community\Models\CommunityCoach;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CoachController extends PanelBaseController
{
    public function index(Request $request): View
    {
        $community = $this->currentCommunity($request);
        $this->authorize('view', $community);

        $coaches = $community->coaches()->with('user')->latest()->get();
        $developmentLevels = DB::table('development_levels')->get();

        return view('panel.coaches.index', compact('community', 'coaches', 'developmentLevels'));
    }

    public function store(PanelCoachRequest $request): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        $data = $request->validated();
        $data['started_at'] = $data['started_at'] ?? now()->toDateString();
        $data['status'] = $data['status'] ?? 'active';

        $community->coaches()->create($data);

        return redirect()
            ->route('panel.coaches.index')
            ->with('success', 'Pelatih berhasil ditambahkan.');
    }

    public function destroy(Request $request, CommunityCoach $coach): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);
        $this->assertBelongsToCommunity($community, $coach->community_id);

        $coach->delete();

        return redirect()
            ->route('panel.coaches.index')
            ->with('success', 'Pelatih berhasil dihapus.');
    }
}
