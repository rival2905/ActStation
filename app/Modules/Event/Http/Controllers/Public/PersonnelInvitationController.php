<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\PersonnelInvitationJoinRequest;
use App\Modules\Event\Services\DraftPersonnelInvitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonnelInvitationController extends Controller
{
    public function __construct(private readonly DraftPersonnelInvitations $invites) {}

    public function show(Request $request, string $token): View
    {
        return view('events.personnel-invitation', $this->invites->show($token, $request->user()));
    }

    public function login(Request $request, string $token): RedirectResponse
    {
        $this->invites->find($token);
        $request->session()->put('url.intended', route('personnel-invitations.show', $token));

        return redirect()->route('panel.login');
    }

    public function register(Request $request, string $token): RedirectResponse
    {
        $this->invites->find($token);
        $request->session()->put('personnel_registration_return', $token);

        return redirect()->route('register');
    }

    public function join(PersonnelInvitationJoinRequest $request, string $token): RedirectResponse
    {
        $status = $this->invites->join($token, $request->user(), (int) $request->validated('role_id'));

        return redirect()->route('personnel-invitations.show', $token)->with('success', match ($status) {
            'joined' => 'Anda tercatat pada roster draft tenaga pendukung. Belum berarti peserta resmi.',
            'pending' => 'Keanggotaan pendukung menunggu persetujuan pengurus komunitas. Setelah disetujui, buka link ini dan klik Gabung Roster.',
            default => 'Keanggotaan tidak aktif atau ditolak. Hubungi pengurus komunitas.',
        });
    }
}
