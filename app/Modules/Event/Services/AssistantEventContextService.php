<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\AssistantEventContext;
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCommitteeMember;
use App\Modules\Event\Models\EventParticipant;

class AssistantEventContextService implements AssistantEventContext
{
    public function forUser(User $user, string $scope = 'all'): array
    {
        abort_unless($user->is_active, 403);
        $result = [];
        if (in_array($scope, ['all', 'personal'], true)) {
            $personal = EventParticipant::query()->whereHas('event')->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)->orWhereHas('members', fn ($member) => $member->where('user_id', $user->id));
            });

            $result = ['personal' => [
                'total' => (clone $personal)->count(),
                'items' => $personal->with('event:id,title,starts_at,ends_at')->latest('id')->limit(5)
                    ->get(['id', 'event_id', 'status'])->map(fn (EventParticipant $participant): array => [
                        'event' => $participant->event->title,
                        'status' => $participant->status,
                        'starts_at' => $participant->event->starts_at?->toIso8601String(),
                        'ends_at' => $participant->event->ends_at?->toIso8601String(),
                    ])->all(),
            ]];
        }
        if (in_array($scope, ['all', 'organizer'], true)) {
            $committee = EventCommitteeMember::query()->select('event_id')->where('user_id', $user->id)->where('status', 'approved')
                ->whereHas('role', fn ($role) => $role->whereColumn('event_committee_roles.event_id', 'event_committee_members.event_id')
                    ->where('is_active', true)->whereJsonContains('capabilities', 'teams.view'));
            $managed = Event::query()->where(function ($query) use ($user, $committee): void {
                $query->whereIn('id', $committee);
                if ($user->hasRole(['organizer', 'superadmin'])) {
                    $query->orWhere('user_id', $user->id);
                }
            });
            $result['organizer'] = ['total' => (clone $managed)->count(), 'items' => $managed->latest('id')->limit(5)
                ->get(['id', 'user_id', 'title', 'status'])->map(function (Event $event) use ($user): array {
                    abort_unless(app(EventCommitteeAccess::class)->allows($event, $user, 'teams.view'), 403);

                    return [
                        'title' => $event->title, 'status' => $event->status,
                        'registrations_by_status' => $event->participants()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status')->all(),
                    ];
                })->all()];
        }

        // Region overseers receive regional community metrics, never unscoped event totals.
        if (in_array($scope, ['all', 'admin'], true) && $user->isInternal() && (! $user->hasRole('region_overseer') || $user->hasRole(['admin', 'superadmin'])) && $user->can('event.view')) {
            $result['admin']['events_by_status'] = Event::query()->selectRaw('status, COUNT(*) AS aggregate')
                ->groupBy('status')->pluck('aggregate', 'status')->all();
        }

        return $result;
    }
}
