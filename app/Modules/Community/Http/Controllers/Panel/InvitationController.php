<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Modules\Community\Http\Requests\Panel\PanelInvitationRequest;
use App\Modules\Community\Models\CommunityInvitation;
use App\Modules\Community\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController extends PanelBaseController
{
    public function __construct(
        protected InvitationService $invitationService
    ) {}

    public function store(PanelInvitationRequest $request): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        $result = $this->invitationService->createInvitation(
            $community,
            $request->validated(),
            $request->user()
        );

        $inviteUrl = url('/invite/'.$result['plain_token']);

        return redirect()
            ->route('panel.athletes.index')
            ->with('success', 'Link undangan berhasil dibuat.')
            ->with('invite_url', $inviteUrl)
            ->with('plain_token', $result['plain_token']);
    }

    public function revoke(Request $request, CommunityInvitation $invitation): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);
        $this->assertBelongsToCommunity($community, $invitation->community_id);

        $this->invitationService->revokeInvitation($invitation);

        return redirect()
            ->route('panel.athletes.index')
            ->with('success', 'Undangan berhasil dicabut.');
    }
}
