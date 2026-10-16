<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Community\Exceptions\InvalidInvitationException;
use App\Modules\Community\Http\Requests\Public\JoinInvitationRequest;
use App\Modules\Community\Http\Requests\Public\PublicInvitationRequest;
use App\Modules\Community\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(
        protected InvitationService $invitationService
    ) {}

    public function show(string $token): View
    {
        try {
            $invitation = $this->invitationService->findValidInvitation($token, request()->user());
        } catch (InvalidInvitationException) {
            abort(404, 'Undangan tidak ditemukan atau sudah tidak berlaku.');
        }

        $community = $invitation->community;

        $invitationContext = $this->invitationService->invitationFormContext($invitation);

        $alreadyJoined = $this->invitationService->alreadyJoined($invitation, request()->user());

        $canUseSavedDetails = ! $alreadyJoined && $this->invitationService->savedRosterDetails($invitation, request()->user()) !== null;

        $educationDetail = request()->user()?->educations()->where('community_id', $invitation->community_id)->value('education_detail');

        return view('public.invite', compact('invitation', 'community', 'token', 'invitationContext', 'alreadyJoined', 'canUseSavedDetails', 'educationDetail'));
    }

    public function submit(PublicInvitationRequest $request, string $token): RedirectResponse
    {
        try {
            $result = $this->invitationService->registerWithInvitation($token, $request->validated());
        } catch (InvalidInvitationException) {
            abort(404, 'Undangan tidak ditemukan atau sudah tidak berlaku.');
        }

        $redirect = redirect()
            ->route('panel.login')
            ->with('success', 'Permohonan berhasil dikirim. Menunggu verifikasi pengurus.');

        if (! ($result['honeypot_triggered'] ?? false)) {
            $request->session()->put('url.intended', route('invitations.public.show', $token));
        }

        return $redirect;
    }

    public function login(Request $request, string $token): RedirectResponse
    {
        try {
            $this->invitationService->findValidInvitation($token);
        } catch (InvalidInvitationException) {
            abort(404, 'Undangan tidak ditemukan atau sudah tidak berlaku.');
        }

        $request->session()->put('url.intended', route('invitations.public.show', $token));

        return redirect()->route('panel.login');
    }

    public function join(JoinInvitationRequest $request, string $token): RedirectResponse
    {
        try {
            $this->invitationService->joinWithInvitation(
                $token,
                $request->user(),
                $request->validated(),
            );
        } catch (InvalidInvitationException) {
            abort(404, 'Undangan tidak ditemukan atau sudah tidak berlaku.');
        }

        return redirect()
            ->route('invitations.public.show', $token)
            ->with('success', 'Undangan berhasil diterima. Status keanggotaan dan pemeriksaan mengikuti proses yang berlaku.');
    }
}
