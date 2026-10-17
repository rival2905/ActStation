<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\CommunityLookup;
use App\Contracts\TeamDocumentInspection;
use App\Contracts\TeamPersonnelProvider;
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamInspectionService
{
    public function __construct(private readonly CommunityLookup $communities, private readonly TeamDocumentInspection $vault) {}

    public function registration(Event $event, string $kind, string $id, bool $lock = false): EventParticipant|RegistrationDraft
    {
        abort_unless(in_array($kind, ['draft', 'participant'], true), 404);
        $query = ($kind === 'draft' ? RegistrationDraft::query() : EventParticipant::query())->where('event_id', $event->id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $registration = $query->findOrFail($id);
        abort_if($registration instanceof RegistrationDraft && $registration->isSubmitted(), 404);
        $registration->load('category');
        abort_unless(($registration instanceof RegistrationDraft && $registration->event_category_id === null)
            || ($registration->category && (int) $registration->category->event_id === (int) $event->id), 404);

        return $registration;
    }

    public function inspect(Event $event, string $kind, string $id, string $workspace, bool $canReview): array
    {
        $registration = $this->registration($event, $kind, $id);
        $payload = $registration instanceof RegistrationDraft ? ($registration->payload ?? []) : $registration->getAttributes();
        $community = $registration->community_id ? $this->communities->find((int) $registration->community_id) : null;
        $members = $this->members($registration);
        $users = User::query()->whereIn('id', array_column($members, 'user_id'))->get()->keyBy('id');
        $result = [];
        foreach ($members as $member) {
            $user = $users->get($member['user_id']);
            if (! $user) {
                continue;
            }
            $result[] = [
                'user_id' => $user->id, 'name' => $user->name, 'role' => $member['team_role'] ?? 'player',
                'birth_date' => $user->birth_date?->format('d M Y'), 'gender' => $user->gender,
                'email' => $user->email, 'phone' => $user->phone_number,
                'jersey_number' => $member['jersey_number'] ?? null, 'position' => $member['position'] ?? null,
                'documents' => $this->documents($event, $registration, $kind, (string) $user->id, $workspace),
            ];
        }

        return [
            'event' => $event, 'workspace' => $workspace, 'kind' => $kind, 'registration' => $registration,
            'canReview' => $canReview && $registration instanceof EventParticipant && $registration->status !== 'cancelled',
            'team' => ['name' => $payload['team_name'] ?? 'Tanpa Nama', 'status' => $kind === 'draft' ? 'draft' : $registration->status,
                'category_name' => $registration->category?->name ?? 'Belum dipilih', 'community_name' => $community?->name,
                'contact_person' => $payload['contact_person'] ?? null, 'contact_phone' => $payload['contact_phone'] ?? null],
            'members' => $result,
            'personnel' => $this->personnel($registration, $event, $kind, $workspace),
            'documents' => $this->documents($event, $registration, $kind, 'team', $workspace),
        ];
    }

    private function personnel(RegistrationDraft|EventParticipant $registration, Event $event, string $kind, string $workspace): array
    {
        if ($registration instanceof EventParticipant) {
            return $registration->personnel()->with('user:id,name')->get()->map(function ($row) use ($event, $registration, $workspace): array {
                $hasLicense = ! empty($row->license_path);

                return [
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'role_id' => $row->role_id,
                    'name' => $row->user?->name ?? 'Akun tidak tersedia',
                    'role_name' => $row->role_name,
                    'has_license' => $hasLicense,
                    'license_url' => $hasLicense ? route(($workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.').'teams.personnel-license.download', [$event, 'participant', $registration->id, $row->id]) : null,
                ];
            })->all();
        }
        $roles = collect(app(TeamPersonnelProvider::class)->roles())->keyBy('id');
        $selection = $registration->payload['personnel'] ?? [];
        $users = User::whereIn('id', array_column($selection, 'user_id'))->pluck('name', 'id');
        $stagedLicenses = $registration->files['personnel_licenses'] ?? [];

        return array_map(function ($row) use ($users, $roles, $stagedLicenses, $event, $registration, $workspace): array {
            $uid = $row['user_id'] ?? 0;
            $rid = $row['role_id'] ?? 0;
            $hasLicense = ! empty($stagedLicenses[$uid][$rid]['staged_path']);

            return [
                'id' => null,
                'user_id' => $uid,
                'role_id' => $rid,
                'name' => $users->get($uid, 'Akun tidak tersedia'),
                'role_name' => $roles->get($rid)['name'] ?? 'Peran tidak tersedia',
                'has_license' => $hasLicense,
                'license_url' => $hasLicense ? route(($workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.').'teams.personnel-license.download', [$event, 'draft', $registration->id, "{$uid}-{$rid}"]) : null,
            ];
        }, $selection);
    }

    public function downloadPersonnelLicense(Event $event, string $kind, string $id, string $personnelKey): StreamedResponse
    {
        $registration = $this->registration($event, $kind, $id);
        if ($registration instanceof EventParticipant) {
            $personnel = $registration->personnel()->whereKey((int) $personnelKey)->firstOrFail();
            abort_unless(! empty($personnel->license_path) && Storage::disk('local')->exists($personnel->license_path), 404);
            $ext = pathinfo($personnel->license_path, PATHINFO_EXTENSION) ?: 'jpg';

            return Storage::disk('local')->download($personnel->license_path, "lisensi-{$personnel->role_name}-{$personnel->user_id}.{$ext}");
        }

        $parts = explode('-', $personnelKey);
        abort_unless(count($parts) === 2, 404);
        [$uid, $rid] = [(int) $parts[0], (int) $parts[1]];
        $meta = $registration->files['personnel_licenses'][$uid][$rid] ?? null;
        abort_unless($meta && ! empty($meta['staged_path']) && Storage::disk('local')->exists($meta['staged_path']), 404);
        $name = $meta['name'] ?? "lisensi-personel-{$uid}.jpg";

        return Storage::disk('local')->download($meta['staged_path'], $name);
    }

    public function download(Event $event, string $kind, string $id, string $owner, string $code): StreamedResponse
    {
        $registration = $this->registration($event, $kind, $id);
        $this->documentType($registration, $owner, $code);
        $staged = $this->staged($registration, $owner, $code);
        if ($staged) {
            abort_unless(Storage::disk('local')->exists($staged['staged_path']), 404);

            return Storage::disk('local')->download($staged['staged_path'], basename($staged['name'] ?? $code));
        }
        abort_if($owner === 'team' && $kind === 'draft', 404);

        return $this->vault->download($owner === 'team' ? $registration->getMorphClass() : (new User)->getMorphClass(), $owner === 'team' ? (int) $registration->id : (int) $owner, $code);
    }

    public function review(Event $event, string $kind, string $id, string $owner, string $code, array $decision, int $reviewer): void
    {
        abort_unless($kind === 'participant', 403, 'Draft hanya dapat dilihat.');
        DB::transaction(function () use ($event, $kind, $id, $owner, $code, $decision, $reviewer): void {
            $registration = $this->registration($event, $kind, $id, true);
            abort_if($registration->status === 'cancelled', 403);
            $this->documentType($registration, $owner, $code);
            $this->vault->review($owner === 'team' ? $registration->getMorphClass() : (new User)->getMorphClass(), $owner === 'team' ? (int) $registration->id : (int) $owner, $code, $decision['version'], $decision['status'], $decision['notes'] ?? null, $reviewer);
        });
    }

    private function members(EventParticipant|RegistrationDraft $registration): array
    {
        return $registration instanceof RegistrationDraft
            ? array_values(array_filter((array) ($registration->payload['members'] ?? []), fn ($m): bool => is_array($m) && isset($m['user_id']) && is_numeric($m['user_id'])))
            : $registration->members()->get()->map(fn ($m): array => $m->getAttributes())->all();
    }

    private function documentType(EventParticipant|RegistrationDraft $registration, string $owner, string $code): DocumentType
    {
        if ($owner !== 'team') {
            abort_unless(ctype_digit($owner) && in_array((int) $owner, array_map('intval', array_column($this->members($registration), 'user_id')), true), 404);
            abort_unless(User::query()->whereKey((int) $owner)->exists(), 404);
        }
        abort_unless(in_array($code, (array) ($registration->category?->required_documents ?? []), true), 404);
        $type = DocumentType::query()->where('code', $code)->where('scope', $owner === 'team' ? 'team' : 'athlete')->firstOrFail();
        $community = $registration->community_id ? $this->communities->find((int) $registration->community_id) : null;
        abort_unless($type->appliesToEntity($community?->subtype ?? 'individual'), 404);

        return $type;
    }

    private function documents(Event $event, EventParticipant|RegistrationDraft $registration, string $kind, string $owner, string $workspace): array
    {
        $rows = [];
        $community = $registration->community_id ? $this->communities->find((int) $registration->community_id) : null;
        $types = DocumentType::query()->whereIn('code', (array) ($registration->category?->required_documents ?? []))->where('scope', $owner === 'team' ? 'team' : 'athlete')->orderBy('sort_order')->get();
        foreach ($types as $type) {
            if (! $type->appliesToEntity($community?->subtype ?? 'individual')) {
                continue;
            }
            $staged = $this->staged($registration, $owner, $type->code);
            $row = $staged ? ['exists' => Storage::disk('local')->exists($staged['staged_path']), 'status' => 'pending', 'notes' => null, 'version' => null] : (($kind === 'draft' && $owner === 'team') ? null : $this->vault->find($owner === 'team' ? $registration->getMorphClass() : (new User)->getMorphClass(), $owner === 'team' ? (int) $registration->id : (int) $owner, $type->code));
            $row ??= ['exists' => false, 'status' => 'missing', 'notes' => null, 'version' => null];
            $params = ['event' => $event->id, 'kind' => $kind, 'id' => $registration->id, 'owner' => $owner, 'code' => $type->code];
            $prefix = $workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.';
            $rows[] = array_merge($row, ['code' => $type->code, 'name' => $type->name,
                'download_url' => $row['exists'] ? route($prefix.'teams.inspection.download', $params) : null,
                'review_url' => $kind === 'participant' ? route($prefix.'teams.inspection.review', $params) : null]);
        }

        return $rows;
    }

    private function staged(EventParticipant|RegistrationDraft $registration, string $owner, string $code): ?array
    {
        if (! $registration instanceof RegistrationDraft) {
            return null;
        }
        $files = $registration->files ?? [];
        $meta = $owner === 'team' ? ($files['team_documents'][$code] ?? null) : ($files['athlete_documents'][$owner][$code] ?? null);
        if (! is_array($meta)) {
            return null;
        }
        $path = $meta['staged_path'] ?? '';
        $prefix = 'registration_drafts/'.$registration->id.'/'.($owner === 'team' ? 'team/' : 'athlete/');
        abort_unless(is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..') && ! str_contains($path, '\\'), 404);

        return $meta;
    }
}
