<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\CommitteeMemberRequest;
use App\Modules\Event\Http\Requests\CommitteeRoleRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCommitteeMember;
use App\Modules\Event\Models\EventCommitteeRole;
use App\Modules\Event\Services\EventCommitteeAccess;
use App\Modules\Event\Services\EventCommitteeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventCommitteeController extends Controller
{
    public function __construct(private readonly EventCommitteeService $committee) {}

    public function mine(Request $request): View
    {
        return view('panel.organizer.committee.mine', ['members' => $this->committee->mine($request->user())]);
    }

    public function index(Request $request, Event $event): View
    {
        return view('panel.organizer.committee.index', $this->committee->listing($event, $request->user()) + ['event' => $event, 'workspace' => 'organizer', 'invitationUrl' => session('committee_invitation_url')]);
    }

    public function store(CommitteeRoleRequest $request, Event $event): RedirectResponse
    {
        $this->committee->saveRole($event, $request->validated() + ['capabilities' => [], 'is_active' => true]);

        return back()->with('success', 'Jabatan panitia ditambahkan. Periksa akses sebelum membagikan undangan.');
    }

    public function update(CommitteeRoleRequest $request, Event $event, EventCommitteeRole $role): RedirectResponse
    {
        $this->committee->saveRole($event, $request->validated() + ['capabilities' => []], $role);

        return back()->with('success', 'Jabatan dan akses diperbarui. Berlaku untuk panitia yang sudah disetujui pada jabatan ini.');
    }

    public function defaults(Event $event): RedirectResponse
    {
        app(EventCommitteeAccess::class)->authorize($event);
        $this->committee->defaults($event);

        return back()->with('success', 'Jabatan bawaan ditambahkan. Jabatan yang sudah ada tidak ditimpa. Akses dapat disesuaikan.');
    }

    public function invite(Event $event, EventCommitteeRole $role): RedirectResponse
    {
        app(EventCommitteeAccess::class)->authorize($event);

        return back()->with('committee_invitation_url', $this->committee->invite($event, $role))->with('success', 'Link berlaku 14 hari sejak dibuat. Calon panitia tetap menunggu persetujuan.');
    }

    public function revoke(Event $event, EventCommitteeRole $role): RedirectResponse
    {
        app(EventCommitteeAccess::class)->authorize($event);
        $this->committee->revoke($event, $role);

        return back()->with('success', 'Link undangan dicabut. Panitia yang sudah disetujui tidak dihapus.');
    }

    public function decide(CommitteeMemberRequest $request, Event $event, EventCommitteeMember $member): RedirectResponse
    {
        $this->committee->decide($event, $member, $request->validated('action'), $request->user());

        return back()->with('success', 'Status panitia diperbarui.');
    }
}
