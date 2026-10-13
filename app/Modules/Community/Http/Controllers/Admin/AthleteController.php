<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\AthleteRequest;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Services\AthleteMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AthleteController extends Controller
{
    public function __construct(
        protected AthleteMembershipService $membershipService
    ) {}

    public function index(Community $community): View
    {
        $athletes = $community->athletes()->with('user')->latest()->get();

        return view('admin.communities.athletes.index', compact('community', 'athletes'));
    }

    public function store(AthleteRequest $request, Community $community): RedirectResponse
    {
        $data = $request->validated();
        $initiatedBy = $data['initiated_by'] ?? 'community';
        unset($data['initiated_by']);

        $this->membershipService->addOrInviteAthlete(
            $community,
            (int) $data['user_id'],
            $data,
            $initiatedBy
        );

        return redirect()
            ->route('admin.communities.athletes.index', $community)
            ->with('success', 'Atlet berhasil ditambahkan.');
    }

    public function release(Community $community, CommunityAthlete $athlete): RedirectResponse
    {
        abort_if($athlete->community_id !== $community->id, 404);

        $this->membershipService->release($athlete);

        return redirect()
            ->route('admin.communities.athletes.index', $community)
            ->with('success', 'Atlet berhasil dilepas dari komunitas.');
    }
}
