<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Contracts\CommunityLookup;
use App\Contracts\RosterProvider;
use App\Http\Controllers\Controller;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Exceptions\RosterValidationException;
use App\Modules\Event\Http\Requests\Panel\ResubmitRosterRequest;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\RegistrationDraftService;
use App\Modules\Event\Services\RegistrationInspectionSummary;
use App\Modules\Event\Services\RegistrationRosterRevisionService;
use App\Modules\Event\Services\TeamPersonnelRoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CommunityRosterController extends Controller
{
    public function __construct(
        private readonly RosterProvider $rosterProvider,
        private readonly CommunityLookup $communityLookup,
    ) {}

    public function show(EventParticipant $participant): View
    {
        $this->authorizeParticipantAccess($participant);

        $participant->loadMissing([
            'event',
            'category',
            'members.user',
            'paymentVerifiedBy',
        ]);

        $viewerId = (int) Auth::id();
        $activeCommunity = $this->communityLookup->findForUser($viewerId);
        $isManager = Auth::user()?->hasRole('superadmin')
            || ($activeCommunity && (int) $activeCommunity->id === (int) $participant->community_id);
        $canRevise = $isManager && $participant->status === 'revision_needed';
        $inspection = app(RegistrationInspectionSummary::class)->inspect($participant);
        $canWithdraw = app(RegistrationDraftService::class)->canWithdraw($participant, Auth::user());

        return view('panel.registrations.roster', compact('participant', 'viewerId', 'isManager', 'canRevise', 'inspection', 'canWithdraw'));
    }

    public function withdraw(EventParticipant $participant, RegistrationDraftService $draftService): RedirectResponse
    {
        $draft = $draftService->withdraw($participant, Auth::user());

        return redirect()->route('panel.community-events.create', [
            'slug' => $draft->event->slug, 'draft_id' => $draft->id, 'step' => 'roster',
        ])->with('success', 'Pengajuan ditarik ke draf. Data tim, roster, dan berkas tetap tersimpan; silakan edit lalu ajukan ulang.');
    }

    public function revise(EventParticipant $participant): View
    {
        $this->authorizeRevisionAccess($participant);

        $participant->loadMissing([
            'event',
            'category',
            'members.user',
        ]);

        if (app(RegistrationDraftService::class)->canWithdraw($participant, Auth::user())) {
            return view('panel.registrations.restore-draft', compact('participant'));
        }

        $category = $participant->category;
        $eligibleAthletes = $participant->community_id !== null
            ? $this->rosterProvider->eligibleAthletes((int) $participant->community_id, (int) $participant->event_category_id)
            : collect();

        $personnelEvent = clone $participant->event;
        $personnelEvent->personnel_requirements = $participant->personnel_requirements ?? [];
        $personnelOptions = app(TeamPersonnelRoster::class)->options($personnelEvent, (int) $participant->community_id);
        $personnelSelected = $participant->personnel()->get(['user_id', 'role_id'])->toArray();

        return view('panel.registrations.revise', compact('participant', 'category', 'eligibleAthletes', 'personnelOptions', 'personnelSelected'));
    }

    public function resubmit(
        ResubmitRosterRequest $request,
        EventParticipant $participant,
        RegistrationRosterRevisionService $revisionService
    ): RedirectResponse {
        $this->authorizeRevisionAccess($participant);

        $members = (array) $request->validated('members');
        $teamDocuments = (array) ($request->file('team_documents') ?? []);
        $athleteDocuments = (array) ($request->file('athlete_documents') ?? []);

        try {
            $user = $request->user();
            $revisionService->resubmit(
                $participant,
                $members,
                $teamDocuments,
                $athleteDocuments,
                $user,
                session('current_community_id') ? (int) session('current_community_id') : null,
                $request->has('personnel_present') ? ($request->validated('personnel') ?? []) : null
            );

            return redirect()
                ->route('panel.community-events.roster', $participant)
                ->with('success', 'Roster dan berkas perbaikan berhasil dikirim ulang.');
        } catch (RosterValidationException $e) {
            $errors = ! empty($e->errors) ? $e->errors : ['error' => $e->getMessage()];

            return back()->withInput()->withErrors($errors)->with('error', $e->getMessage());
        }
    }

    private function authorizeParticipantAccess(EventParticipant $participant): void
    {
        $userId = Auth::id();
        if ($participant->user_id === $userId || Auth::user()?->hasRole('superadmin')) {
            return;
        }

        // Atlet dalam roster juga boleh melihat roster (PRD_EXTERNAL §4.3 D)
        $isMember = $participant->members()->where('user_id', $userId)->exists();
        if ($isMember) {
            return;
        }

        if ($participant->community_id) {
            $isOfficial = CommunityOfficial::query()
                ->where('community_id', $participant->community_id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->exists();

            if ($isOfficial) {
                return;
            }
        }

        abort(403, 'Anda tidak memiliki hak akses pada berkas pendaftaran ini.');
    }

    private function authorizeRevisionAccess(EventParticipant $participant): void
    {
        abort_if($participant->status !== 'revision_needed', 403, 'Pendaftaran ini tidak dalam status revisi.');

        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if ($user->hasRole('superadmin')) {
            return;
        }

        if (! $participant->community_id) {
            abort(403, 'Hanya manajer aktif komunitas yang dapat merevisi pendaftaran ini.');
        }

        $userCommunity = $this->communityLookup->findForUser((int) $user->id);
        if (! $userCommunity || (int) $userCommunity->id !== (int) $participant->community_id) {
            abort(403, 'Hanya manajer aktif komunitas terpilih yang dapat merevisi pendaftaran ini.');
        }
    }
}
