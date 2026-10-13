<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Modules\Community\Http\Requests\Panel\PanelProfileRequest;
use App\Modules\Community\Services\CommunityLifecycle;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;

class ProfileController extends PanelBaseController
{
    public function show(Request $request): View
    {
        $community = $this->currentCommunity($request);
        $this->authorize('view', $community);

        $community->loadMissing(['documents', 'officials.user']);

        return view('panel.profile', compact('community'));
    }

    public function update(PanelProfileRequest $request): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($community->logo && Storage::disk('public')->exists($community->logo)) {
                Storage::disk('public')->delete($community->logo);
            }
            $data['logo'] = $request->file('logo')->store('community_logos', 'public');
        }

        $community->update($data);

        return redirect()
            ->route('panel.profile.show')
            ->with('success', 'Profil komunitas berhasil diperbarui.');
    }

    public function requestVerification(Request $request, CommunityLifecycle $lifecycle): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        try {
            $lifecycle->submit($community);

            return redirect()
                ->route('panel.profile.show')
                ->with('success', 'Permohonan verifikasi komunitas berhasil diajukan.');
        } catch (DomainException|InvalidArgumentException $e) {
            return redirect()
                ->route('panel.profile.show')
                ->with('error', $e->getMessage());
        }
    }
}
