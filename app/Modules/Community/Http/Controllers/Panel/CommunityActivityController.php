<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Modules\Community\Http\Requests\Panel\PanelCommunityActivityRequest;
use App\Modules\Community\Models\CommunityActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityActivityController extends PanelBaseController
{
    public function store(PanelCommunityActivityRequest $request): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['status'] = $data['status'] ?? 'scheduled';

        $community->activities()->create($data);

        return redirect()
            ->route('panel.profile.show')
            ->with('success', 'Program aktivitas berhasil ditambahkan.');
    }

    public function update(PanelCommunityActivityRequest $request, CommunityActivity $activity): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);
        $this->assertBelongsToCommunity($community, (int) $activity->community_id);

        $data = $request->validated();
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $activity->update($data);

        return redirect()
            ->route('panel.profile.show')
            ->with('success', 'Program aktivitas berhasil diperbarui.');
    }

    public function destroy(Request $request, CommunityActivity $activity): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);
        $this->assertBelongsToCommunity($community, (int) $activity->community_id);

        $activity->delete();

        return redirect()
            ->route('panel.profile.show')
            ->with('success', 'Program aktivitas berhasil dihapus.');
    }
}
