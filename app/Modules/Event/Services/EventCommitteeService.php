<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCommitteeInvitation;
use App\Modules\Event\Models\EventCommitteeMember;
use App\Modules\Event\Models\EventCommitteeRole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventCommitteeService
{
    public function mine(User $user): LengthAwarePaginator
    {
        abort_unless($user->is_active, 403);

        return EventCommitteeMember::where('user_id', $user->id)->whereHas('event')->with(['event:id,title,starts_at', 'role'])->latest()->paginate(20);
    }

    public function listing(Event $event, User $user): array
    {
        $access = app(EventCommitteeAccess::class);
        $canManage = $access->owner($event, $user);
        $query = EventCommitteeMember::where('event_id', $event->id)->with(['user:id,name', 'role']);
        if (! $canManage) {
            $query->where('user_id', $user->id);
        }
        $members = $query->latest()->paginate(20);
        $own = EventCommitteeMember::where('event_id', $event->id)->where('user_id', $user->id)->exists();
        abort_unless($canManage || ($user->is_active && $own), 403);
        $roles = $canManage ? EventCommitteeRole::where('event_id', $event->id)->orderBy('id')->get() : collect();
        $menuLinks = [];
        foreach (['teams.view' => 'teams.index', 'documents.manage' => 'documents.index', 'schedule.manage' => 'tournament.index', 'results.manage' => 'results.index'] as $cap => $route) {
            if ($access->allows($event, $user, $cap)) {
                $menuLinks[] = EventCommitteeAccess::CAPABILITIES[$cap] + ['url' => route('panel.organizer.'.$route, $event)];
            }
        }

        return compact('roles', 'members', 'canManage', 'menuLinks') + ['capabilities' => EventCommitteeAccess::CAPABILITIES, 'presets' => EventCommitteeAccess::PRESETS];
    }

    public function saveRole(Event $event, array $data, ?EventCommitteeRole $role = null): EventCommitteeRole
    {
        return DB::transaction(function () use ($event, $data, $role): EventCommitteeRole {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($role) {
                abort_unless((int) $role->event_id === (int) $event->id, 404);
                $role = EventCommitteeRole::whereKey($role->id)->lockForUpdate()->firstOrFail();
                $role->update($data);

                return $role;
            }

            return EventCommitteeRole::create(['event_id' => $event->id] + $data);
        });
    }

    public function defaults(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            foreach (EventCommitteeAccess::PRESETS as $preset) {
                EventCommitteeRole::firstOrCreate(['event_id' => $event->id, 'name' => $preset['name']], $preset + ['is_active' => true]);
            }
        });
    }

    public function invite(Event $event, EventCommitteeRole $role): string
    {
        return DB::transaction(function () use ($event, $role): string {
            $role = $this->lockedRole($event, $role);
            abort_unless($role->is_active, 422, 'Aktifkan jabatan sebelum membuat undangan.');
            $invite = EventCommitteeInvitation::where('event_committee_role_id', $role->id)->whereNull('revoked_at')->where('expires_at', '>', now())->latest('id')->first();
            if (! $invite) {
                $token = Str::random(64);
                $invite = EventCommitteeInvitation::create(['event_committee_role_id' => $role->id, 'token' => $token, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(14)]);
            }

            return route('committee-invitations.show', $invite->token);
        });
    }

    public function revoke(Event $event, EventCommitteeRole $role): void
    {
        DB::transaction(function () use ($event, $role): void {
            $this->lockedRole($event, $role);
            EventCommitteeInvitation::where('event_committee_role_id', $role->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        });
    }

    public function invitation(string $token): EventCommitteeInvitation
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/D', $token), 404);
        $invite = EventCommitteeInvitation::with('role.event')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now())->firstOrFail();
        abort_unless($invite->role?->is_active && $invite->role->event && ! in_array($invite->role->event->status, ['cancelled', 'completed'], true), 404);

        return $invite;
    }

    public function join(string $token, User $user): void
    {
        $invite = $this->invitation($token);
        DB::transaction(function () use ($invite, $token, $user): void {
            $this->lockedRole($invite->role->event, $invite->role);
            $this->invitation($token);
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->is_active, 403);
            EventCommitteeMember::firstOrCreate(['event_committee_role_id' => $invite->role->id, 'user_id' => $user->id], ['event_id' => $invite->role->event_id, 'status' => 'pending', 'consented_at' => now()]);
        });
    }

    public function decide(Event $event, EventCommitteeMember $member, string $action, User $actor): void
    {
        DB::transaction(function () use ($event, $member, $action, $actor): void {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $member = EventCommitteeMember::whereKey($member->id)->where('event_id', $event->id)->lockForUpdate()->firstOrFail();
            $role = EventCommitteeRole::whereKey($member->event_committee_role_id)->lockForUpdate()->firstOrFail();
            $target = User::whereKey($member->user_id)->lockForUpdate()->firstOrFail();
            if ($action === 'approve' && (! $role->is_active || ! $target->is_active)) {
                throw ValidationException::withMessages(['action' => 'Akun dan jabatan harus aktif sebelum disetujui.']);
            }
            if (($action === 'end' && $member->status !== 'approved') || ($action !== 'end' && $member->status !== 'pending')) {
                throw ValidationException::withMessages(['action' => 'Status panitia sudah berubah. Muat ulang sebelum memutuskan.']);
            }
            $member->update(['status' => match ($action) {
                'approve' => 'approved', 'reject' => 'rejected', 'end' => 'ended'
            }, 'decided_by' => $actor->id, 'decided_at' => now()]);
        });
    }

    private function lockedRole(Event $event, EventCommitteeRole $role): EventCommitteeRole
    {
        Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

        return EventCommitteeRole::whereKey($role->id)->where('event_id', $event->id)->lockForUpdate()->firstOrFail();
    }
}
