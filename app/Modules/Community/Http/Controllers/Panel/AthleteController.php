<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Modules\Community\Http\Requests\Panel\PanelAthleteIndexRequest;
use App\Modules\Community\Http\Requests\Panel\PanelAthleteRequest;
use App\Modules\Community\Http\Requests\Panel\SchoolAthleteExtracurricularRequest;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Services\AthleteMembershipService;
use App\Modules\Community\Services\SchoolExtracurricularService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AthleteController extends PanelBaseController
{
    public function __construct(
        protected AthleteMembershipService $membershipService
    ) {}

    public function index(PanelAthleteIndexRequest $request, SchoolExtracurricularService $extracurriculars): View
    {
        $community = $this->currentCommunity($request);
        $this->authorize('view', $community);
        $filter = $request->validated();
        $selectedExtracurricularId = isset($filter['extracurricular_id']) ? (int) $filter['extracurricular_id'] : null;
        $schoolExtracurricularUnits = $extracurriculars->unitsForDirectory($community);
        abort_if($selectedExtracurricularId !== null && ! $schoolExtracurricularUnits->contains('id', $selectedExtracurricularId), 404);

        $athletes = $community->athletes()
            ->with(['user.province', 'user.city', 'community', 'developmentLevel'])
            ->whereIn('status', ['active', 'left'])
            ->latest()
            ->get();

        $pendingAthletes = $community->athletes()
            ->with(['user.province', 'user.city', 'community', 'developmentLevel'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        $incomingRegistrations = $pendingAthletes->where('initiated_by', 'athlete')->values();
        $pendingRequests = $pendingAthletes->where('initiated_by', 'community')->values();

        $rawInvitations = $community->invitations()
            ->select([
                'id',
                'community_id',
                'invite_type',
                'is_global',
                'name',
                'email',
                'max_registrations',
                'registrations_count',
                'status',
                'expires_at',
                'registered_at',
                'registered_user_id',
                'encrypted_token',
                'created_by',
                'created_at',
                'updated_at',
            ])
            ->latest()
            ->get();

        $globalLinks = $rawInvitations->where('is_global', true)->where('status', 'pending')->values();
        $invitations = $rawInvitations->where('is_global', false)->values();
        $developmentLevels = DB::table('development_levels')->get();
        $schoolExtracurricularsByAthlete = $extracurriculars->membershipsForDirectory($community, $athletes);
        $allAthletesCount = $athletes->count();
        foreach ($schoolExtracurricularUnits as $unit) {
            $unit->member_count = $schoolExtracurricularsByAthlete->filter(fn ($memberships) => $memberships->contains('unit_id', $unit->id))->count();
        }
        if ($selectedExtracurricularId !== null) {
            $athletes = $athletes->filter(fn ($athlete) => $schoolExtracurricularsByAthlete->get($athlete->id, collect())->contains('unit_id', $selectedExtracurricularId))->values();
        }

        return view('panel.athletes.index', compact(
            'community',
            'athletes',
            'pendingAthletes',
            'incomingRegistrations',
            'pendingRequests',
            'invitations',
            'globalLinks',
            'developmentLevels',
            'schoolExtracurricularsByAthlete',
            'schoolExtracurricularUnits',
            'selectedExtracurricularId',
            'allAthletesCount'
        ));
    }

    public function addExtracurricular(SchoolAthleteExtracurricularRequest $request, int $athlete, SchoolExtracurricularService $extracurriculars): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);
        $extracurriculars->addMember($community, $athlete, (int) $request->validated('extracurricular_id'), $request->user());

        return redirect()->route('panel.athletes.index')->with('success', 'Keanggotaan ekskul ditambahkan. Roster turnamen tidak berubah.');
    }

    public function store(PanelAthleteRequest $request): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        $data = $request->validated();
        $this->membershipService->addOrInviteAthlete(
            $community,
            (int) $data['user_id'],
            $data,
            'community'
        );

        return redirect()
            ->route('panel.athletes.index')
            ->with('success', 'Undangan atlet berhasil dikirim.');
    }

    public function approve(Request $request, CommunityAthlete $athlete): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);
        $this->assertBelongsToCommunity($community, $athlete->community_id);

        try {
            $this->membershipService->approveByManager($athlete, $request->user());
        } catch (DomainException $e) {
            return redirect()
                ->route('panel.athletes.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('panel.athletes.index')
            ->with('success', 'Permohonan keanggotaan atlet berhasil disetujui.');
    }

    public function reject(Request $request, CommunityAthlete $athlete): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);
        $this->assertBelongsToCommunity($community, $athlete->community_id);

        try {
            $this->membershipService->rejectByManager($athlete, $request->user());
        } catch (DomainException $e) {
            return redirect()
                ->route('panel.athletes.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('panel.athletes.index')
            ->with('success', 'Permohonan keanggotaan atlet ditolak.');
    }

    public function release(Request $request, CommunityAthlete $athlete): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);
        $this->assertBelongsToCommunity($community, $athlete->community_id);

        $this->membershipService->release($athlete);

        return redirect()
            ->route('panel.athletes.index')
            ->with('success', 'Atlet berhasil dilepas dari komunitas.');
    }

    public function resend(Request $request, CommunityAthlete $athlete): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);
        $this->assertBelongsToCommunity($community, $athlete->community_id);

        $this->membershipService->resend($athlete);

        return redirect()
            ->route('panel.athletes.index')
            ->with('success', 'Undangan atlet berhasil dikirim ulang.');
    }
}
