<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Community\Exceptions\InvalidInvitationException;
use App\Modules\Community\Http\Requests\Public\PublicInvitationRequest;
use App\Modules\Community\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(
        protected InvitationService $invitationService
    ) {}

    public function show(string $token): View
    {
        try {
            $invitation = $this->invitationService->findValidInvitation($token);
        } catch (InvalidInvitationException) {
            abort(404, 'Undangan tidak ditemukan atau sudah tidak berlaku.');
        }

        $community = $invitation->community;

        return view('public.invite', compact('invitation', 'community', 'token'));
    }

    public function submit(PublicInvitationRequest $request, string $token): RedirectResponse
    {
        try {
            $this->invitationService->registerWithInvitation($token, $request->validated());
        } catch (InvalidInvitationException) {
            abort(404, 'Undangan tidak ditemukan atau sudah tidak berlaku.');
        }

        return redirect()
            ->route('panel.login')
            ->with('success', 'Permohonan berhasil dikirim. Menunggu verifikasi pengurus.');
    }
}
