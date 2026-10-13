<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\CommunityActivityRequest;
use App\Modules\Community\Models\CommunityActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityActivityController extends Controller
{
    public function index(Request $request): View
    {
        $activities = CommunityActivity::query()
            ->with('community')
            ->latest('scheduled_at')
            ->paginate(15);

        return view('admin.community-activities.index', compact('activities'));
    }

    public function store(CommunityActivityRequest $request): RedirectResponse
    {
        CommunityActivity::create($request->validated());

        return redirect()
            ->route('admin.community-activities.index')
            ->with('success', 'Aktivitas komunitas berhasil ditambahkan.');
    }

    public function update(CommunityActivityRequest $request, CommunityActivity $activity): RedirectResponse
    {
        $activity->update($request->validated());

        return redirect()
            ->route('admin.community-activities.index')
            ->with('success', 'Aktivitas komunitas berhasil diperbarui.');
    }

    public function destroy(CommunityActivity $activity): RedirectResponse
    {
        $activity->delete();

        return redirect()
            ->route('admin.community-activities.index')
            ->with('success', 'Aktivitas komunitas berhasil dihapus.');
    }
}
