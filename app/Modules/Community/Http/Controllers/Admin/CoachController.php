<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\CoachRequest;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityCoach;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CoachController extends Controller
{
    public function index(Community $community): View
    {
        $coaches = $community->coaches()->with('user')->latest()->get();

        return view('admin.communities.coaches.index', compact('community', 'coaches'));
    }

    public function store(CoachRequest $request, Community $community): RedirectResponse
    {
        $community->coaches()->create($request->validated());

        return redirect()
            ->route('admin.communities.coaches.index', $community)
            ->with('success', 'Pelatih berhasil ditambahkan.');
    }

    public function destroy(Community $community, CommunityCoach $coach): RedirectResponse
    {
        abort_if($coach->community_id !== $community->id, 404);

        $coach->delete();

        return redirect()
            ->route('admin.communities.coaches.index', $community)
            ->with('success', 'Pelatih berhasil dihapus.');
    }
}
