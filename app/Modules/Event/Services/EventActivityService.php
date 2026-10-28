<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\ActivityBilling;
use App\Contracts\CommunityLookup;
use App\Models\User;
use App\Modules\Event\Models\ActivityMember;
use App\Modules\Event\Models\ActivityRegistration;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventActivity;
use App\Modules\Event\Models\EventParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventActivityService
{
    public function save(Event $event, User $user, array $data, ?EventActivity $activity = null): EventActivity
    {
        return DB::transaction(function () use ($event, $user, $data, $activity): EventActivity {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $activity = $activity ? EventActivity::whereKey($activity->id)->lockForUpdate()->firstOrFail() : null;

            return $this->saveLocked($event, $user, $data, $activity);
        });
    }

    private function saveLocked(Event $event, User $user, array $data, ?EventActivity $activity): EventActivity
    {
        abort_if($activity && (int) $activity->event_id !== (int) $event->id, 404);
        $access = app(EventCommitteeAccess::class);
        if (! $access->owner($event, $user)) {
            abort(403, 'Hanya pemilik event yang dapat mengelola kegiatan.');
        }

        Validator::make($data, [
            'min_members' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'max_members' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'fee' => ['sometimes', 'integer', 'min:0', 'max:999999999'],
        ])->validate();
        if (($data['max_members'] ?? $activity?->max_members ?? $data['min_members'] ?? 1) < ($data['min_members'] ?? $activity?->min_members ?? 1)) {
            throw ValidationException::withMessages(['max_members' => 'Maksimum anggota tidak boleh di bawah minimum.']);
        }
        $startsAt = $data['starts_at'] ?? $activity?->starts_at;
        $endsAt = $data['ends_at'] ?? $activity?->ends_at;
        Validator::make(['starts_at' => $startsAt, 'ends_at' => $endsAt], [
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ])->validate();

        if ($activity === null) {
            $validated = Validator::make($data, [
                'name' => ['required', 'string', 'max:255'],
                'eligibility_scope' => ['sometimes', 'required', 'string', 'in:all,academic,school,university'],
                'description' => ['nullable', 'string'],
                'requirements' => ['nullable', 'string'],
                'starts_at' => ['nullable', 'date'],
                'ends_at' => ['nullable', 'date'],
                'registration_deadline' => ['nullable', 'date'],
                'capacity' => ['required', 'integer', 'min:0'],
                'min_members' => ['nullable', 'integer', 'min:1'],
                'max_members' => ['nullable', 'integer', 'min:1'],
                'fee_mode' => ['nullable', 'string', 'in:separate,included_team,included_institution'],
                'included_slots' => ['nullable', 'integer', 'min:0'],
                'fee' => ['nullable', 'integer', 'min:0'],
                'allow_extra' => ['nullable', 'boolean'],
                'is_open' => ['nullable', 'boolean'],
            ])->validate();

            $minMembers = (int) ($validated['min_members'] ?? 1);
            $maxMembers = (int) ($validated['max_members'] ?? $minMembers);

            return EventActivity::create([
                'event_id' => $event->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'requirements' => $validated['requirements'] ?? null,
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
                'registration_deadline' => $validated['registration_deadline'] ?? null,
                'capacity' => (int) ($validated['capacity'] ?? 0),
                'min_members' => $minMembers,
                'max_members' => $maxMembers,
                'fee_mode' => $validated['fee_mode'] ?? 'separate',
                'eligibility_scope' => $validated['eligibility_scope'] ?? 'all',
                'included_slots' => (int) ($validated['included_slots'] ?? 0),
                'fee' => (int) ($validated['fee'] ?? 0),
                'allow_extra' => (bool) ($validated['allow_extra'] ?? false),
                'is_open' => (bool) ($validated['is_open'] ?? true),
            ]);
        }

        $validated = Validator::make($data, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'eligibility_scope' => ['sometimes', 'required', 'string', 'in:all,academic,school,university'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'registration_deadline' => ['nullable', 'date'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:0'],
            'min_members' => ['nullable', 'integer', 'min:1'],
            'max_members' => ['nullable', 'integer', 'min:1'],
            'fee_mode' => ['nullable', 'string', 'in:separate,included_team,included_institution'],
            'included_slots' => ['nullable', 'integer', 'min:0'],
            'fee' => ['nullable', 'integer', 'min:0'],
            'allow_extra' => ['nullable', 'boolean'],
            'is_open' => ['nullable', 'boolean'],
        ])->validate();

        if ($activity->registrations()->exists()) {
            if (array_key_exists('eligibility_scope', $data) && $data['eligibility_scope'] !== $activity->eligibility_scope) {
                throw ValidationException::withMessages(['eligibility_scope' => 'Sasaran peserta terkunci setelah ada pendaftaran.']);
            }
            foreach (['included_slots', 'allow_extra'] as $field) {
                if (array_key_exists($field, $data) && (int) $data[$field] !== (int) $activity->$field) {
                    throw ValidationException::withMessages([$field => 'Kebijakan paket terkunci setelah ada pendaftaran.']);
                }
            }
            if (array_key_exists('fee', $data) && (int) $data['fee'] !== (int) $activity->fee) {
                throw ValidationException::withMessages(['fee' => 'Biaya kegiatan tidak dapat diubah setelah ada pendaftaran.']);
            }
            if (array_key_exists('fee_mode', $data) && $data['fee_mode'] !== $activity->fee_mode) {
                throw ValidationException::withMessages(['fee_mode' => 'Mode biaya kegiatan tidak dapat diubah setelah ada pendaftaran.']);
            }
            if (array_key_exists('min_members', $data) && (int) $data['min_members'] !== (int) $activity->min_members) {
                throw ValidationException::withMessages(['min_members' => 'Batas minimum anggota tidak dapat diubah setelah ada pendaftaran.']);
            }
            if (array_key_exists('max_members', $data) && (int) $data['max_members'] !== (int) $activity->max_members) {
                throw ValidationException::withMessages(['max_members' => 'Batas maksimum anggota tidak dapat diubah setelah ada pendaftaran.']);
            }
            if (array_key_exists('capacity', $data)) {
                $activeCount = $activity->registrations()->whereNotIn('status', ['cancelled', 'rejected'])->count();
                if ((int) $data['capacity'] < $activeCount) {
                    throw ValidationException::withMessages(['capacity' => 'Kapasitas tidak boleh lebih kecil dari pendaftaran aktif saat ini.']);
                }
            }
        }

        $activity->update($validated);

        return $activity->fresh();
    }

    public function register(Event $event, EventActivity $activity, User $user, array $data): ActivityRegistration
    {
        abort_unless($user->is_active, 403);
        abort_unless((int) $activity->event_id === (int) $event->id, 404);
        $data = Validator::make($data, [
            'request_key' => ['required', 'uuid'], 'team_name' => ['nullable', 'string', 'max:255'],
            'parent_participant_id' => ['nullable', 'integer', 'min:1'],
            'members' => ['required', 'array', 'min:1', 'max:100'],
            'members.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'consent' => ['required', 'accepted'], 'use_extra' => ['sometimes', 'boolean'],
        ])->validate();
        $requestKey = $data['request_key'];

        return DB::transaction(function () use ($event, $activity, $user, $data, $requestKey): ActivityRegistration {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $lockedActivity = EventActivity::where('id', $activity->id)->lockForUpdate()->firstOrFail();
            if (! in_array($event->status, ['published', 'ongoing'], true) || ! $event->registration_open || ! $lockedActivity->is_open
                || ($lockedActivity->registration_deadline && $lockedActivity->registration_deadline->lte(now()))) {
                throw ValidationException::withMessages(['activity' => 'Pendaftaran kegiatan ditutup.']);
            }
            $existing = $lockedActivity->registrations()->where('user_id', $user->id)->where('request_key', $requestKey)->first();
            if ($existing) {
                return $existing;
            }

            $activeCount = ActivityRegistration::where('event_activity_id', $lockedActivity->id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->lockForUpdate()
                ->count();

            if ($lockedActivity->capacity > 0 && $activeCount >= $lockedActivity->capacity) {
                throw ValidationException::withMessages(['capacity' => 'Kapasitas kegiatan telah penuh.']);
            }

            $members = $data['members'] ?? [$user->id];
            if (! is_array($members) || empty($members)) {
                $members = [$user->id];
            }
            $memberCount = count($members);

            if ($memberCount < (int) $lockedActivity->min_members) {
                throw ValidationException::withMessages(['members' => "Jumlah anggota minimal {$lockedActivity->min_members} orang."]);
            }
            if ($memberCount > (int) $lockedActivity->max_members) {
                throw ValidationException::withMessages(['members' => "Jumlah anggota maksimal {$lockedActivity->max_members} orang."]);
            }

            $amount = 0;
            $allocationKey = null;
            $parentParticipantId = $data['parent_participant_id'] ?? null;
            $communityId = null;

            if ($lockedActivity->fee_mode === 'separate') {
                $amount = (int) $lockedActivity->fee;
                $allocationKey = null;
                if ($parentParticipantId) {
                    $parent = EventParticipant::where('id', $parentParticipantId)->where('event_id', $event->id)->lockForUpdate()->firstOrFail();
                    $this->authorizeParent($parent, $user);
                    $communityId = $parent->community_id;
                }
            } elseif ($lockedActivity->fee_mode === 'included_team') {
                if (! $parentParticipantId) {
                    throw ValidationException::withMessages(['parent_participant_id' => 'Peserta induk wajib disertakan.']);
                }
                $parent = EventParticipant::where('id', $parentParticipantId)->where('event_id', $event->id)->lockForUpdate()->first();
                if (! $parent) {
                    throw ValidationException::withMessages(['parent_participant_id' => 'Peserta induk tidak ditemukan.']);
                }
                if (! in_array($parent->status, ['confirmed', 'attended'], true)) {
                    throw ValidationException::withMessages(['parent_participant_id' => 'Peserta induk belum terkonfirmasi.']);
                }

                $this->authorizeParent($parent, $user);

                $communityId = $parent->community_id;
                $teamKey = "team:{$parent->id}";

                $usedSlots = ActivityRegistration::where('event_activity_id', $lockedActivity->id)
                    ->where('allocation_key', $teamKey)
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->lockForUpdate()
                    ->count();

                if ($usedSlots < (int) $lockedActivity->included_slots) {
                    $amount = 0;
                    $allocationKey = $teamKey;
                } else {
                    $useExtra = filter_var($data['use_extra'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    if ($lockedActivity->allow_extra && $useExtra) {
                        $amount = (int) $lockedActivity->fee;
                        $allocationKey = null;
                    } else {
                        throw ValidationException::withMessages(['use_extra' => 'Kuota tim telah habis.']);
                    }
                }
            } elseif ($lockedActivity->fee_mode === 'included_institution') {
                if (! $parentParticipantId) {
                    throw ValidationException::withMessages(['parent_participant_id' => 'Peserta induk wajib disertakan.']);
                }
                $parent = EventParticipant::where('id', $parentParticipantId)->where('event_id', $event->id)->lockForUpdate()->first();
                if (! $parent) {
                    throw ValidationException::withMessages(['parent_participant_id' => 'Peserta induk tidak ditemukan.']);
                }
                if (! in_array($parent->status, ['confirmed', 'attended'], true)) {
                    throw ValidationException::withMessages(['parent_participant_id' => 'Peserta induk belum terkonfirmasi.']);
                }

                $this->authorizeParent($parent, $user);
                if (! $parent->community_id) {
                    throw ValidationException::withMessages(['parent_participant_id' => 'Jatah instansi memerlukan instansi.']);
                }

                $communityId = $parent->community_id;
                $instKey = "institution:{$event->id}:{$parent->community_id}";

                $usedSlots = ActivityRegistration::where('event_activity_id', $lockedActivity->id)
                    ->where('allocation_key', $instKey)
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->lockForUpdate()
                    ->count();

                if ($usedSlots < (int) $lockedActivity->included_slots) {
                    $amount = 0;
                    $allocationKey = $instKey;
                } else {
                    $useExtra = filter_var($data['use_extra'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    if ($lockedActivity->allow_extra && $useExtra) {
                        $amount = (int) $lockedActivity->fee;
                        $allocationKey = null;
                    } else {
                        throw ValidationException::withMessages(['use_extra' => 'Kuota institusi telah habis.']);
                    }
                }
            }

            $this->assertInstitutionEligible($lockedActivity, $communityId ? (int) $communityId : null);

            $applicantConsent = filter_var($data['consent'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $initialStatus = 'awaiting_consent';
            if ($applicantConsent && count($members) === 1 && (int) $members[0] === (int) $user->id) {
                $initialStatus = 'pending_review';
            }

            $registration = ActivityRegistration::create([
                'event_activity_id' => $lockedActivity->id,
                'user_id' => $user->id,
                'parent_participant_id' => $parentParticipantId,
                'community_id' => $communityId,
                'team_name' => $data['team_name'] ?? null,
                'amount' => $amount,
                'allocation_key' => $allocationKey,
                'status' => $initialStatus,
                'request_key' => $requestKey,

            ]);

            foreach ($members as $memberUserId) {
                $isApplicant = (int) $memberUserId === (int) $user->id;
                $acceptedAt = ($isApplicant && $applicantConsent) ? now() : null;

                ActivityMember::create([
                    'registration_id' => $registration->id,

                    'user_id' => $memberUserId,
                    'accepted_at' => $acceptedAt,
                ]);
            }

            return $registration;
        });
    }

    public function accept(Event $event, EventActivity $activity, ActivityRegistration $registration, User $user): void
    {
        $this->scope($event, $activity, $registration);
        DB::transaction(function () use ($event, $activity, $registration, $user): void {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            EventActivity::whereKey($activity->id)->lockForUpdate()->firstOrFail();
            $registration = ActivityRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            if (! in_array($registration->status, ['awaiting_consent', 'pending_review'], true) || ! in_array($event->status, ['published', 'ongoing'], true)) {
                throw ValidationException::withMessages(['status' => 'Pendaftaran tidak menerima persetujuan.']);
            }
            if (! $user->is_active) {
                abort(403, 'Pengguna tidak aktif.');
            }

            $member = ActivityMember::where('registration_id', $registration->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $member) {
                abort(403, 'Bukan anggota dari pendaftaran ini.');
            }

            if (! $member->accepted_at) {
                $member->update(['accepted_at' => now()]);
            }

            $hasPending = ActivityMember::where('registration_id', $registration->id)
                ->whereNull('accepted_at')
                ->exists();

            if (! $hasPending && $registration->status === 'awaiting_consent') {
                $registration->update(['status' => 'pending_review']);
            }
        });
    }

    public function decide(Event $event, EventActivity $activity, ActivityRegistration $registration, User $user, string $decision): void
    {
        $this->scope($event, $activity, $registration);
        $access = app(EventCommitteeAccess::class);
        if (! $access->allows($event, $user, 'teams.review')) {
            abort(403, 'Anda tidak memiliki hak untuk memutuskan status pendaftaran.');
        }

        Validator::make(['decision' => $decision], ['decision' => ['in:approve,reject']])->validate();
        DB::transaction(function () use ($event, $activity, $registration, $decision): void {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $activity = EventActivity::whereKey($activity->id)->lockForUpdate()->firstOrFail();
            $registration = ActivityRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            if (! in_array($event->status, ['published', 'ongoing'], true) || ! in_array($registration->status, ['pending_review', 'awaiting_consent', 'needs_review'], true)) {
                throw ValidationException::withMessages(['status' => 'Pendaftaran tidak dapat diputuskan.']);
            }
            if ($decision === 'approve') {
                $this->assertInstitutionEligible($activity, $registration->community_id ? (int) $registration->community_id : null);
                $hasPendingConsent = ActivityMember::where('registration_id', $registration->id)
                    ->whereNull('accepted_at')
                    ->exists();

                if ($hasPendingConsent || ! $registration->members()->exists() || $registration->members()->whereDoesntHave('user')->exists()
                    || $registration->members()->whereHas('user', fn ($q) => $q->where('is_active', false))->exists()) {
                    throw ValidationException::withMessages(['consent' => 'Semua anggota harus menyetujui keikutsertaan terlebih dahulu.']);
                }

                if ($registration->allocation_key && ! $this->parentEligible($event, $registration)) {
                    throw ValidationException::withMessages(['parent' => 'Pendaftaran induk perlu ditinjau.']);
                }
                app(EventQuestionService::class)->assertActivityComplete($event, $registration);
                $decision = 'confirmed';
                if ($registration->amount > 0) {
                    $billing = app(ActivityBilling::class);
                    $billing->invoice((int) $registration->user_id, (int) $registration->id, (int) $registration->amount);
                    $decision = $billing->paid((int) $registration->id, (int) $registration->amount) ? 'confirmed' : 'pending_payment';
                }
            } else {
                $decision = 'rejected';
            }

            $registration->update(['status' => $decision]);
        });
    }

    public function reconcileParent(EventParticipant $parent, bool $removed = false): void
    {
        if (! $removed && in_array($parent->status, ['confirmed', 'attended'], true)) {
            return;
        }

        $registrations = ActivityRegistration::whereHas('activity', fn ($q) => $q->where('event_id', $parent->event_id))
            ->whereNotNull('allocation_key')->where(function ($q) use ($parent): void {
                $q->where('parent_participant_id', $parent->id);
                if ($parent->community_id) {
                    $q->orWhere('allocation_key', 'institution:'.$parent->event_id.':'.$parent->community_id);
                }
            })
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->get();

        foreach ($registrations as $reg) {
            $activity = $reg->activity ?? EventActivity::find($reg->event_activity_id);
            if ($activity && $activity->fee_mode === 'included_institution') {
                $hasAlternate = EventParticipant::where('event_id', $parent->event_id)
                    ->where('community_id', $parent->community_id)
                    ->where('id', '!=', $parent->id)
                    ->whereIn('status', ['confirmed', 'attended'])
                    ->exists();

                if ($hasAlternate) {
                    continue;
                }
            }

            $reg->update(['status' => 'needs_review']);
        }
    }

    public function catalog(Event $event, ?User $user = null): array
    {
        abort_unless(in_array($event->status, ['published', 'ongoing', 'completed'], true), 404);
        $activities = EventActivity::where('event_id', $event->id)->get();
        $myRegistrations = ActivityRegistration::whereHas('activity', fn ($q) => $q->where('event_id', $event->id))
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user?->id ?? 0)->orWhereHas('members', fn ($q) => $q->where('user_id', $user?->id ?? 0));
            })->with(['members.user:id,name', 'activity'])->get();
        foreach ($myRegistrations as $registration) {
            $registration->setAttribute('is_escrow_waived', $registration->amount > 0
                && ! empty(app(ActivityBilling::class)->find((int) $registration->id)['meta']['is_escrow_waived']));
        }
        $lookup = app(CommunityLookup::class);
        $eligibleParents = EventParticipant::where('event_id', $event->id)->whereIn('status', ['confirmed', 'attended'])->get()
            ->filter(fn ($parent) => $user && ($parent->community_id ? $lookup->canManage((int) $user->id, (int) $parent->community_id) : (int) $parent->user_id === (int) $user->id))->values();

        $communities = $eligibleParents->pluck('community_id')->filter()->unique()->mapWithKeys(fn ($id) => [$id => $lookup->find((int) $id)]);
        foreach ($activities as $activity) {
            $activity->setRelation('eligibleParents', $eligibleParents->filter(fn ($parent) => $activity->acceptsInstitution($communities->get($parent->community_id)))->values());
        }

        return compact('event', 'activities', 'myRegistrations', 'eligibleParents');
    }

    public function manage(Event $event, User $user): array
    {
        $access = app(EventCommitteeAccess::class);
        if (! $access->allows($event, $user, 'teams.view')) {
            abort(403, 'Anda tidak memiliki hak untuk mengelola kegiatan event ini.');
        }

        $activities = EventActivity::where('event_id', $event->id)
            ->with(['registrations.members'])
            ->get();
        $registrations = ActivityRegistration::whereHas('activity', fn ($q) => $q->where('event_id', $event->id))->with('members.user:id,name')->get();

        $totalActivities = $activities->count();
        $totalRegistrations = ActivityRegistration::whereHas('activity', fn ($q) => $q->where('event_id', $event->id))->count();
        $confirmedRegistrations = ActivityRegistration::whereHas('activity', fn ($q) => $q->where('event_id', $event->id))->where('status', 'confirmed')->count();
        $pendingRegistrations = ActivityRegistration::whereHas('activity', fn ($q) => $q->where('event_id', $event->id))->whereIn('status', ['pending_review', 'awaiting_consent'])->count();

        return [
            'event' => $event,
            'activities' => $activities,
            'registrations' => $registrations,
            'stats' => [
                'total_activities' => $totalActivities,
                'total_registrations' => $totalRegistrations,
                'confirmed_registrations' => $confirmedRegistrations,
                'pending_registrations' => $pendingRegistrations,
            ],
        ];
    }

    private function scope(Event $event, EventActivity $activity, ActivityRegistration $registration): void
    {
        abort_unless((int) $activity->event_id === (int) $event->id && (int) $registration->event_activity_id === (int) $activity->id, 404);
    }

    private function assertInstitutionEligible(EventActivity $activity, ?int $communityId): void
    {
        $community = $communityId ? app(CommunityLookup::class)->find($communityId) : null;
        if (! $activity->acceptsInstitution($community)) {
            throw ValidationException::withMessages(['parent_participant_id' => 'Pilih tim induk dari sekolah atau kampus yang sesuai sasaran kegiatan.']);
        }
    }

    private function authorizeParent(EventParticipant $parent, User $user): void
    {
        if ($parent->community_id) {
            $lookup = app(CommunityLookup::class);
            $community = $lookup->find((int) $parent->community_id);
            abort_unless($community && $community->is_active && $community->status === 'verified' && $lookup->canManage((int) $user->id, (int) $parent->community_id), 403);
        } else {
            abort_unless((int) $parent->user_id === (int) $user->id, 403);
        }
    }

    private function parentEligible(Event $event, ActivityRegistration $registration): bool
    {
        $query = EventParticipant::where('event_id', $event->id)->whereIn('status', ['confirmed', 'attended']);

        return str_starts_with($registration->allocation_key, 'institution:')
            ? $query->where('community_id', $registration->community_id)->exists()
            : $query->whereKey($registration->parent_participant_id)->exists();
    }
}
