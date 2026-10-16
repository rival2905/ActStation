<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\CommitteeJoinRequest;
use App\Modules\Event\Models\EventCommitteeMember;
use App\Modules\Event\Services\EventCommitteeAccess;
use App\Modules\Event\Services\EventCommitteeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommitteeInvitationController extends Controller
{
    public function __construct(private readonly EventCommitteeService $committee) {}

    public function show(Request $request, string $token): View
    {
        $invite = $this->committee->invitation($token);
        $membership = $request->user() ? EventCommitteeMember::where('event_committee_role_id', $invite->event_committee_role_id)->where('user_id', $request->user()->id)->first() : null;

        return view('events.committee-invitation', ['event' => $invite->role->event, 'role' => $invite->role, 'token' => $token, 'membership' => $membership, 'capabilities' => EventCommitteeAccess::CAPABILITIES]);
    }

    public function login(Request $request, string $token): RedirectResponse
    {
        $this->committee->invitation($token);
        $request->session()->put('url.intended', route('committee-invitations.show', $token));

        return redirect()->route('panel.login');
    }

    public function register(Request $request, string $token): RedirectResponse
    {
        $this->committee->invitation($token);
        $request->session()->put('committee_registration_return', $token);

        return redirect()->route('register');
    }

    public function join(CommitteeJoinRequest $request, string $token): RedirectResponse
    {
        $this->committee->join($token, $request->user());

        return redirect()->route('committee-invitations.show', $token)->with('success', 'Pengajuan panitia tercatat. Tunggu persetujuan pemilik event sebelum mendapat akses.');
    }
}
