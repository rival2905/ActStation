<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Modules\Community\Http\Requests\Panel\PanelAthleteRequest;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Services\AthleteMembershipService;
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

    public function index(Request $request): View
    {
        $community = $this->currentCommunity($request);
        $this->authorize('view', $community);

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

        return view('panel.athletes.index', compact(
            'community',
            'athletes',
            'pendingAthletes',
            'incomingRegistrations',
            'pendingRequests',
            'invitations',
            'globalLinks',
            'developmentLevels'
        ));
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
