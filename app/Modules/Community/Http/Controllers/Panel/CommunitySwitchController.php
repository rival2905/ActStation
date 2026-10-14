<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\Panel\PanelSwitchCommunityRequest;
use Illuminate\Http\RedirectResponse;

class CommunitySwitchController extends Controller
{
    public function switch(PanelSwitchCommunityRequest $request): RedirectResponse
    {
        $communityId = (int) $request->validated('community_id');

        session(['current_community_id' => $communityId]);

        return redirect()
            ->route('panel.profile.show')
            ->with('success', 'Entitas komunitas aktif berhasil dialihkan.');
    }
}
