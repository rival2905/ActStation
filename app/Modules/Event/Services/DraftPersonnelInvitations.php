<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\CommunityLookup;
use App\Contracts\TeamPersonnelProvider;
use App\Models\User;
use App\Modules\Event\Models\DraftPersonnelInvitation;
use App\Modules\Event\Models\RegistrationDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DraftPersonnelInvitations
{
    public function __construct(private readonly RegistrationDraftService $drafts, private readonly TeamPersonnelRoster $roster, private readonly TeamPersonnelProvider $people) {}

    public function create(RegistrationDraft $draft, int $revision): string
    {
        return DB::transaction(function () use ($draft, $revision): string {
            $draft = RegistrationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            abort_unless($revision === (int) $draft->revision, 409, 'Konflik revisi draft. Muat ulang tim.');
            $this->context($draft);
            abort_unless(count($this->allowedRoles($draft)) > 0, 422, 'Panitia belum mengaktifkan peran tenaga pendukung.');
            $this->drafts->retainForInvitation($draft->id);
            $invite = DraftPersonnelInvitation::where('registration_draft_id', $draft->id)->where('event_category_id', $draft->event_category_id)->whereNull('revoked_at')->oldest('id')->first();
            if (! $invite) {
                $token = Str::random(64);
                $invite = DraftPersonnelInvitation::create(['registration_draft_id' => $draft->id, 'event_category_id' => $draft->event_category_id, 'token' => $token, 'token_hash' => hash('sha256', $token)]);
            }

            return route('personnel-invitations.show', $invite->token);
        });
    }

    public function find(string $token): DraftPersonnelInvitation
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/D', $token), 404);
        $invite = DraftPersonnelInvitation::with('draft.event', 'draft.category')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->firstOrFail();
        abort_unless($invite->draft && (int) $invite->event_category_id === (int) $invite->draft->event_category_id, 404);
        $this->context($invite->draft);

        return $invite;
    }

    public function show(string $token, ?User $user): array
    {
        $draft = $this->find($token)->draft;
        $roles = $this->allowedRoles($draft);
        $statuses = [];
        if ($user) {
            foreach ($roles as $role) {
                $statuses[$role['id']] = $this->people->membershipStatus((int) $draft->community_id, (int) $user->id, $role['id']);
            }
        }
        $selected = collect($draft->payload['personnel'] ?? [])->filter(fn ($row) => $user && (int) $row['user_id'] === (int) $user->id)->pluck('role_id')->all();

        return ['draft' => $draft, 'roles' => $roles, 'statuses' => $statuses, 'selectedRoles' => $selected, 'token' => $token];
    }

    public function join(string $token, User $user, int $roleId): string
    {
        $invite = $this->find($token);

        return DB::transaction(function () use ($invite, $token, $user, $roleId): string {
            $draft = RegistrationDraft::whereKey($invite->registration_draft_id)->lockForUpdate()->firstOrFail();
            $this->find($token);
            abort_unless(User::whereKey($user->id)->where('is_active', true)->exists(), 403);
            abort_unless(collect($this->allowedRoles($draft))->contains('id', $roleId), 422, 'Peran tidak diizinkan untuk event ini.');
            $payload = $draft->payload ?? [];
            $selection = $payload['personnel'] ?? [];
            if (collect($selection)->contains(fn ($row) => (int) $row['user_id'] === (int) $user->id && (int) $row['role_id'] === $roleId)) {
                return 'joined';
            }
            $eligible = collect($this->people->eligiblePeople((int) $draft->community_id))->contains('user_id', $user->id);
            if (! $eligible) {
                $status = $this->people->requestMembership((int) $draft->community_id, (int) $user->id, $roleId);
                if ($status !== 'active') {
                    return $status;
                }
            }
            $selection[] = ['user_id' => (int) $user->id, 'role_id' => $roleId];
            $this->roster->validate($draft->event, (int) $draft->community_id, $selection, false);
            $payload['personnel'] = $selection;
            $draft->update(['payload' => $payload, 'revision' => (int) $draft->revision + 1]);

            return 'joined';
        });
    }

    private function allowedRoles(RegistrationDraft $draft): array
    {
        $allowed = collect($draft->event->personnel_requirements ?? [])->pluck('role_id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_filter($this->people->roles(), fn ($role) => $role['is_active'] && in_array((int) $role['id'], $allowed, true)));
    }

    private function context(RegistrationDraft $draft): void
    {
        $community = app(CommunityLookup::class)->find((int) $draft->community_id);
        abort_unless($community?->is_active, 404, 'Komunitas tidak aktif.');
        abort_unless($draft->event_category_id && $this->drafts->validInvitationContext($draft->id, (int) $draft->community_id, (int) $draft->event_category_id), 404, 'Link roster tidak lagi aktif.');
    }
}
