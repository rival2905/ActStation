<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\CommunityLookup;
use App\Contracts\DTOs\CommunitySummary;
use App\Contracts\DTOs\RegistrationDraftContextDto;
use App\Contracts\RegistrationDraftProvider;
use App\Contracts\RosterProvider;
use App\Contracts\TeamPersonnelProvider;
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\Event\Models\RegistrationDraftCandidate;
use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationDraftService implements RegistrationDraftProvider
{
    public function __construct(
        private readonly RosterProvider $rosterProvider,
        private readonly CommunityLookup $communityLookup,
        private readonly RegistrationGuard $registrationGuard,
    ) {}

    public function validInvitationContext(
        string $draftId,
        int $communityId,
        int $eventCategoryId,
        bool $lockForUpdate = false,
    ): ?RegistrationDraftContextDto {
        $query = RegistrationDraft::query()
            ->whereKey($draftId)
            ->where('community_id', $communityId)
            ->where('event_category_id', $eventCategoryId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var RegistrationDraft|null $draft */
        $draft = $query->first();
        if (! $draft || $draft->isExpired() || $draft->isSubmitted()) {
            return null;
        }

        /** @var EventCategory|null $category */
        $category = EventCategory::query()
            ->whereKey($eventCategoryId)
            ->where('event_id', $draft->event_id)
            ->first();
        if (! $category) {
            return null;
        }

        $event = $draft->event;
        if ($event === null || $event->status !== 'published' || ! $event->registration_open
            || $event->registration_deadline?->isPast() || $category->registration_end?->isPast()) {
            return null;
        }

        $community = $this->communityLookup->find($communityId);
        if (! $community || ! $category->acceptsCommunityType($community->subtype, $community->institution_type, $community->school_level)) {
            return null;
        }
        $allowed = $category->allowed_community_subtypes;
        $subtype = is_array($allowed)
            ? (count($allowed) === 1 ? $allowed[0] : $community?->subtype)
            : ($category->community_subtype ?: $community?->subtype);

        return new RegistrationDraftContextDto(
            draftId: (string) $draft->id,
            communityId: (int) $draft->community_id,
            eventId: (int) $draft->event_id,
            eventCategoryId: (int) $draft->event_category_id,
            revision: (int) $draft->revision,
            maxRoster: max(1, (int) ($category->max_roster ?? 1)),
            communitySubtype: $subtype,
            gender: (string) $category->gender,
            minAge: $category->min_age,
            maxAge: $category->max_age,
            eventStartsAt: $event->starts_at?->toDateString(),
            developmentLevelId: $category->development_level_id,
            sportCategoryId: $event->sport_category_id,
            sportSlug: $event->sportCategory?->slug,
        );
    }

    public function addCandidate(string $draftId, int $userId, int $invitationId, array $rosterData = []): bool
    {
        if (! $this->hasCandidateCapacity($draftId, $userId)) {
            throw ValidationException::withMessages([
                'invitation' => 'Kuota calon roster untuk draft ini sudah penuh.',
            ]);
        }

        $inserted = RegistrationDraftCandidate::query()->insertOrIgnore([
            'registration_draft_id' => $draftId,
            'user_id' => $userId,
            'community_invitation_id' => $invitationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;

        $draft = RegistrationDraft::query()->whereKey($draftId)->lockForUpdate()->firstOrFail();
        $payload = $draft->payload;
        $members = (array) ($payload['members'] ?? []);
        if (! collect($members)->contains(fn (array $member): bool => (int) ($member['user_id'] ?? 0) === $userId)) {
            $members[] = ['user_id' => $userId, 'team_role' => 'player', 'jersey_number' => $rosterData['jersey_number'] ?? null, 'position' => $rosterData['position'] ?? '', 'is_captain' => false];
            $payload['members'] = $members;
            $draft->update(['payload' => $payload, 'revision' => $draft->revision + 1]);
        }

        return $inserted;
    }

    public function containsAthlete(string $draftId, int $userId): bool
    {
        $draft = RegistrationDraft::query()->find($draftId);

        return $draft !== null && collect($draft->payload['members'] ?? [])
            ->contains(fn (array $member): bool => (int) ($member['user_id'] ?? 0) === $userId);
    }

    public function retainForInvitation(string $draftId): void
    {
        $draft = RegistrationDraft::query()->whereKey($draftId)->lockForUpdate()->firstOrFail();
        $deadline = collect([$draft->category?->registration_end, $draft->event?->registration_deadline, $draft->event?->starts_at])
            ->filter()->sort()->first();
        if ($deadline !== null && $deadline->isFuture()) {
            $draft->update(['expires_at' => $deadline]);
        }
    }

    public function lockDraft(string $draftId): void
    {
        RegistrationDraft::query()->whereKey($draftId)->lockForUpdate()->first();
    }

    public function hasCandidateCapacity(string $draftId, ?int $userId = null): bool
    {
        /** @var RegistrationDraft|null $draft */
        $draft = RegistrationDraft::query()->whereKey($draftId)->first();
        if (! $draft || $draft->event_category_id === null) {
            return false;
        }

        $category = EventCategory::query()->find($draft->event_category_id);
        if (! $category) {
            return false;
        }

        $maxRoster = max(1, (int) ($category->max_roster ?? 1));
        $selectedIds = collect($draft->payload['members'] ?? [])->pluck('user_id');
        $candidateIds = RegistrationDraftCandidate::query()
            ->where('registration_draft_id', $draftId)
            ->pluck('user_id');
        $occupiedIds = $selectedIds->merge($candidateIds)->filter()->map(fn ($id): int => (int) $id)->unique();

        return ($userId !== null && $selectedIds->map(fn ($id): int => (int) $id)->contains($userId))
            || $occupiedIds->reject(fn (int $id): bool => $id === $userId)->count() < $maxRoster;
    }

    public function authorizeDraft(string $draftId, User $user, ?CommunitySummary $community, Event $event): RegistrationDraft
    {
        if (! Str::isUuid($draftId)) {
            abort(422, 'Format ID draft pendaftaran tidak valid.');
        }

        /** @var RegistrationDraft|null $draft */
        $draft = RegistrationDraft::find($draftId);
        if (! $draft) {
            abort(404, 'Draft pendaftaran tidak ditemukan.');
        }

        if (! $community || (int) $draft->user_id !== (int) $user->id || (int) $draft->community_id !== (int) $community->id || (int) $draft->event_id !== (int) $event->id) {
            abort(403, 'Akses draft pendaftaran ditolak.');
        }

        if ($draft->isExpired()) {
            abort(410, 'Draft pendaftaran sudah kedaluwarsa.');
        }

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(User $user, int $communityId, Event $event, array $data, Request $request): RegistrationDraft
    {
        $draftId = (string) Str::uuid();
        $categoryId = isset($data['event_category_id']) ? (int) $data['event_category_id'] : (isset($data['category']) ? (int) $data['category'] : null);
        if ($categoryId !== null) {
            $category = EventCategory::query()->where('id', $categoryId)->where('event_id', $event->id)->firstOrFail();
            $this->assertCategoryEntityCapacityNotExceeded($category, $communityId, $draftId);
        }

        $payload = $this->extractAllowlistedPayload($data, $categoryId);
        $payload['personnel'] ??= app(TeamPersonnelRoster::class)->initial($event, $communityId, (int) $user->id);
        app(TeamPersonnelRoster::class)->validate($event, $communityId, $payload['personnel'], false);
        $this->assertDraftMembersAllowed($draftId, $communityId, $payload);
        $files = ['team_documents' => [], 'athlete_documents' => []];

        $newlyUploadedPaths = [];
        try {
            [$files, $newlyUploadedPaths, $supersededUploads] = $this->handleUploads(
                $draftId,
                $communityId,
                (int) $event->id,
                $categoryId,
                $payload,
                $files,
                $request
            );

            return RegistrationDraft::create([
                'id' => $draftId,
                'user_id' => $user->id,
                'community_id' => $communityId,
                'event_id' => $event->id,
                'event_category_id' => $categoryId,
                'payload' => $payload,
                'files' => $files,
                'revision' => 1,
                'expires_at' => now()->addDays(7),
            ]);
        } catch (\Throwable $e) {
            foreach ($newlyUploadedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(RegistrationDraft $draft, array $data, int $clientRevision, Request $request): RegistrationDraft
    {
        if ($draft->isSubmitted()) {
            abort(409, 'Draft telah diserahkan dan tidak dapat diubah lagi.');
        }

        if ($draft->isExpired()) {
            abort(410, 'Draft pendaftaran sudah kedaluwarsa.');
        }

        if ((int) $draft->revision !== $clientRevision) {
            abort(409, 'Konflik revisi: draft telah diperbarui oleh sesi lain.');
        }

        $newlyUploadedPaths = [];
        $pathsToDeleteOnSuccess = [];

        try {
            /** @var RegistrationDraft $updatedDraft */
            $updatedDraft = DB::transaction(function () use (
                $draft,
                $data,
                $clientRevision,
                $request,
                &$newlyUploadedPaths,
                &$pathsToDeleteOnSuccess
            ): RegistrationDraft {
                /** @var RegistrationDraft $lockedDraft */
                $lockedDraft = RegistrationDraft::query()
                    ->where('id', $draft->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedDraft->isSubmitted()) {
                    abort(409, 'Draft telah diserahkan dan tidak dapat diubah lagi.');
                }

                if ($lockedDraft->isExpired()) {
                    abort(410, 'Draft pendaftaran sudah kedaluwarsa.');
                }

                if ((int) $lockedDraft->revision !== $clientRevision) {
                    abort(409, 'Konflik revisi: draft telah diperbarui oleh sesi lain.');
                }

                $categoryId = isset($data['event_category_id'])
                    ? (int) $data['event_category_id']
                    : (isset($data['category']) ? (int) $data['category'] : $lockedDraft->event_category_id);

                if ($categoryId !== null) {
                    $category = EventCategory::query()->where('id', $categoryId)->where('event_id', $lockedDraft->event_id)->firstOrFail();
                    $this->assertCategoryEntityCapacityNotExceeded($category, (int) $lockedDraft->community_id, (string) $lockedDraft->id);
                }

                $categoryChanged = $lockedDraft->event_category_id !== null
                    && $categoryId !== null
                    && (int) $lockedDraft->event_category_id !== (int) $categoryId;

                $existingPayload = (array) ($lockedDraft->payload ?? []);
                $newPayload = array_merge($existingPayload, $this->extractAllowlistedPayload($data, $categoryId));
                if ($categoryChanged) {
                    $newPayload['members'] = [];
                }
                app(TeamPersonnelRoster::class)->validate($lockedDraft->event, (int) $lockedDraft->community_id, $newPayload['personnel'] ?? [], false);
                $this->assertDraftMembersAllowed((string) $lockedDraft->id, (int) $lockedDraft->community_id, $newPayload);

                $files = (array) ($lockedDraft->files ?? ['team_documents' => [], 'athlete_documents' => []]);

                if ($categoryChanged) {
                    if (! empty($files['team_documents'])) {
                        foreach ($files['team_documents'] as $doc) {
                            if (! empty($doc['staged_path'])) {
                                $pathsToDeleteOnSuccess[] = $doc['staged_path'];
                            }
                        }
                    }
                    if (! empty($files['athlete_documents'])) {
                        foreach ($files['athlete_documents'] as $athleteDocs) {
                            if (is_array($athleteDocs)) {
                                foreach ($athleteDocs as $doc) {
                                    if (! empty($doc['staged_path'])) {
                                        $pathsToDeleteOnSuccess[] = $doc['staged_path'];
                                    }
                                }
                            }
                        }
                    }
                    if (! empty($files['personnel_licenses'])) {
                        foreach ($files['personnel_licenses'] as $roles) {
                            if (is_array($roles)) {
                                foreach ($roles as $doc) {
                                    if (! empty($doc['staged_path'])) {
                                        $pathsToDeleteOnSuccess[] = $doc['staged_path'];
                                    }
                                }
                            }
                        }
                    }
                    $files = ['team_documents' => [], 'athlete_documents' => [], 'personnel_licenses' => []];
                    $newPayload['members'] = [];
                }

                // Bounded safe staging of requested file deletions
                if (! empty($data['clear_files']) && is_array($data['clear_files'])) {
                    [$files, $pendingDeletions] = $this->stageClearFiles($lockedDraft->id, $files, $data['clear_files']);
                    $pathsToDeleteOnSuccess = array_merge($pathsToDeleteOnSuccess, $pendingDeletions);
                }

                // Upload any new staged files
                [$files, $stagedUploads, $supersededUploads] = $this->handleUploads(
                    $lockedDraft->id,
                    (int) $lockedDraft->community_id,
                    (int) $lockedDraft->event_id,
                    $categoryId,
                    $newPayload,
                    $files,
                    $request
                );
                $newlyUploadedPaths = array_merge($newlyUploadedPaths, $stagedUploads);
                $pathsToDeleteOnSuccess = array_merge($pathsToDeleteOnSuccess, $supersededUploads);

                // Populate attributes onto model so Eloquent encrypted casting serializes them
                $lockedDraft->event_category_id = $categoryId;
                $lockedDraft->payload = $newPayload;
                $lockedDraft->files = $files;
                $lockedDraft->revision = $clientRevision + 1;
                $lockedDraft->updated_at = now();

                $rawAttributes = $lockedDraft->getAttributes();

                $affected = RegistrationDraft::query()
                    ->where('id', $lockedDraft->id)
                    ->where('revision', $clientRevision)
                    ->update([
                        'event_category_id' => $rawAttributes['event_category_id'],
                        'payload' => $rawAttributes['payload'],
                        'files' => $rawAttributes['files'],
                        'revision' => $rawAttributes['revision'],
                        'updated_at' => $rawAttributes['updated_at'],
                    ]);

                if ($affected === 0) {
                    abort(409, 'Konflik revisi: draft telah diperbarui oleh sesi lain.');
                }

                return $lockedDraft->fresh();
            });

            // CAS succeeded: safely delete acknowledged cleared files
            foreach ($pathsToDeleteOnSuccess as $path) {
                Storage::disk('local')->delete($path);
            }

            return $updatedDraft;
        } catch (\Throwable $e) {
            // CAS failed or exception: clean up only newly uploaded files
            foreach ($newlyUploadedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }
    }

    public function deleteDraft(RegistrationDraft $draft): void
    {
        DB::transaction(function () use ($draft): void {
            /** @var RegistrationDraft $lockedDraft */
            $lockedDraft = RegistrationDraft::query()
                ->where('id', $draft->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDraft->isSubmitted()) {
                abort(409, 'Draft telah diserahkan dan tidak dapat dihapus.');
            }

            $lockedDraft->delete();
        });

        Storage::disk('local')->deleteDirectory("registration_drafts/{$draft->id}");
    }

    public function cleanStagedFiles(RegistrationDraft $draft): void
    {
        Storage::disk('local')->deleteDirectory("registration_drafts/{$draft->id}");
    }

    /**
     * Strip private server paths; return metadata only.
     *
     * @param  array<string, mixed>|null  $files
     * @return array<string, mixed>
     */
    public function sanitizeFilesForResponse(?array $files): array
    {
        if (! is_array($files)) {
            return ['team_documents' => [], 'athlete_documents' => [], 'personnel_licenses' => []];
        }

        $sanitized = ['team_documents' => [], 'athlete_documents' => [], 'personnel_licenses' => []];

        if (! empty($files['team_documents']) && is_array($files['team_documents'])) {
            foreach ($files['team_documents'] as $code => $meta) {
                $sanitized['team_documents'][$code] = [
                    'name' => $meta['name'] ?? null,
                    'size' => $meta['size'] ?? null,
                    'mime' => $meta['mime'] ?? null,
                    'field_key' => $meta['field_key'] ?? "team_documents.{$code}",
                ];
            }
        }

        if (! empty($files['athlete_documents']) && is_array($files['athlete_documents'])) {
            foreach ($files['athlete_documents'] as $uid => $docs) {
                if (is_array($docs)) {
                    foreach ($docs as $code => $meta) {
                        $sanitized['athlete_documents'][$uid][$code] = [
                            'name' => $meta['name'] ?? null,
                            'size' => $meta['size'] ?? null,
                            'mime' => $meta['mime'] ?? null,
                            'field_key' => $meta['field_key'] ?? "athlete_documents.{$uid}.{$code}",
                        ];
                    }
                }
            }
        }

        if (! empty($files['personnel_licenses']) && is_array($files['personnel_licenses'])) {
            foreach ($files['personnel_licenses'] as $uid => $roles) {
                if (is_array($roles)) {
                    foreach ($roles as $rid => $meta) {
                        $sanitized['personnel_licenses'][$uid][$rid] = [
                            'name' => $meta['name'] ?? null,
                            'size' => $meta['size'] ?? null,
                            'mime' => $meta['mime'] ?? null,
                            'field_key' => $meta['field_key'] ?? "personnel_licenses.{$uid}.{$rid}",
                        ];
                    }
                }
            }
        }

        return $sanitized;
    }

    /**
     * Resolve missing files from draft staged files and inject into Request files bag.
     */
    public function resolveStagedFiles(RegistrationDraft $draft, Request $request): void
    {
        $stagedFiles = (array) ($draft->files ?? []);

        // 1. Team documents
        $teamUploads = $request->file('team_documents') ?? [];
        if (! empty($stagedFiles['team_documents']) && is_array($stagedFiles['team_documents'])) {
            foreach ($stagedFiles['team_documents'] as $code => $meta) {
                if (! isset($teamUploads[$code])) {
                    $stagedPath = $meta['staged_path'] ?? null;
                    if ($stagedPath && Storage::disk('local')->exists($stagedPath)) {
                        $fullPath = Storage::disk('local')->path($stagedPath);
                        $originalName = $meta['name'] ?? basename($stagedPath);
                        $mime = $meta['mime'] ?? (@mime_content_type($fullPath) ?: 'application/octet-stream');
                        $teamUploads[$code] = new UploadedFile($fullPath, $originalName, $mime, null, true);
                    }
                }
            }
            $request->files->set('team_documents', $teamUploads);

            if (! $request->hasFile('team_document') && count($teamUploads) === 1) {
                $firstFile = reset($teamUploads);
                $request->files->set('team_document', $firstFile);
            }
        }

        // 2. Athlete documents
        $athleteUploads = $request->file('athlete_documents') ?? [];
        $selectedUserIds = array_map(fn ($member): int => is_array($member) ? (int) ($member['user_id'] ?? 0) : 0, (array) $request->input('members', []));
        if (! empty($stagedFiles['athlete_documents']) && is_array($stagedFiles['athlete_documents'])) {
            foreach ($stagedFiles['athlete_documents'] as $uid => $docs) {
                if (in_array((int) $uid, $selectedUserIds, true) && is_array($docs)) {
                    foreach ($docs as $code => $meta) {
                        if (! isset($athleteUploads[$uid][$code])) {
                            $stagedPath = $meta['staged_path'] ?? null;
                            if ($stagedPath && Storage::disk('local')->exists($stagedPath)) {
                                $fullPath = Storage::disk('local')->path($stagedPath);
                                $originalName = $meta['name'] ?? basename($stagedPath);
                                $mime = $meta['mime'] ?? (@mime_content_type($fullPath) ?: 'application/octet-stream');
                                if (! isset($athleteUploads[$uid])) {
                                    $athleteUploads[$uid] = [];
                                }
                                $athleteUploads[$uid][$code] = new UploadedFile($fullPath, $originalName, $mime, null, true);
                            }
                        }
                    }
                }
            }
            $request->files->set('athlete_documents', $athleteUploads);
        }

        // 3. Personnel licenses
        $personnelUploads = $request->file('personnel_licenses') ?? [];
        $selectedPersonnelKeys = collect($request->input('personnel', []))->map(fn ($p): string => ($p['user_id'] ?? '').':'.($p['role_id'] ?? ''))->all();
        if (! empty($stagedFiles['personnel_licenses']) && is_array($stagedFiles['personnel_licenses'])) {
            foreach ($stagedFiles['personnel_licenses'] as $uid => $roles) {
                if (is_array($roles)) {
                    foreach ($roles as $rid => $meta) {
                        if (in_array("{$uid}:{$rid}", $selectedPersonnelKeys, true) && ! isset($personnelUploads[$uid][$rid])) {
                            $stagedPath = $meta['staged_path'] ?? null;
                            if ($stagedPath && Storage::disk('local')->exists($stagedPath)) {
                                $fullPath = Storage::disk('local')->path($stagedPath);
                                $originalName = $meta['name'] ?? basename($stagedPath);
                                $mime = $meta['mime'] ?? (@mime_content_type($fullPath) ?: 'image/jpeg');
                                if (! isset($personnelUploads[$uid])) {
                                    $personnelUploads[$uid] = [];
                                }
                                $personnelUploads[$uid][$rid] = new UploadedFile($fullPath, $originalName, $mime, null, true);
                            }
                        }
                    }
                }
            }
            $request->files->set('personnel_licenses', $personnelUploads);
        }

        (function (): void {
            $this->convertedFiles = null;
        })->call($request);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extractAllowlistedPayload(array $data, ?int $categoryId): array
    {
        $allowed = [
            'team_name',
            'contact_person',
            'contact_phone',
            'notes',
            'registration_source',
            'community_subtype',
            'members',
            'personnel',
        ];

        $payload = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if ($categoryId !== null) {
            $payload['event_category_id'] = $categoryId;
        }

        // Secrets MUST NOT be stored in draft
        unset($payload['invitation_token'], $payload['status'], $payload['participant_id'], $payload['user_id'], $payload['community_id']);

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function assertDraftMembersAllowed(string $draftId, int $communityId, array $payload): void
    {
        $category = isset($payload['event_category_id']) ? EventCategory::find($payload['event_category_id']) : null;
        $genders = User::query()->whereIn('id', collect($payload['members'] ?? [])->pluck('user_id'))->pluck('gender', 'id');
        foreach ((array) ($payload['members'] ?? []) as $index => $member) {
            $userId = (int) ($member['user_id'] ?? 0);
            $eligible = $userId > 0 && $this->rosterProvider->eligibleAthletes($communityId, 0)
                ->contains(fn ($athlete): bool => $athlete->userId === $userId);
            if (! $eligible && ! $this->rosterProvider->isCandidateForDraft($draftId, $communityId, $userId)) {
                throw ValidationException::withMessages([
                    'members' => 'Anggota draft harus berasal dari roster komunitas atau calon draft yang sah.',
                    'athlete_documents' => 'Unggahan dokumen atlet hanya untuk roster komunitas atau calon draft yang sah.',
                ]);
            }
            if ($category && ! $category->acceptsAthleteGender($genders->get($userId))) {
                throw ValidationException::withMessages([
                    "members.{$index}.gender" => 'Jenis kelamin atlet tidak sesuai dengan kategori. Pilih atlet sesuai kategori atau keluarkan dari roster.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $existingFiles
     * @return array{0: array<string, mixed>, 1: list<string>, 2: list<string>}
     */
    private function handleUploads(
        string $draftId,
        int $communityId,
        int $eventId,
        ?int $categoryId,
        array $payload,
        array $existingFiles,
        Request $request
    ): array {
        $hasUploads = $request->hasFile('team_document')
            || ! empty($request->file('team_documents'))
            || ! empty($request->file('athlete_documents'))
            || ! empty($request->file('personnel_licenses'));

        if (! $hasUploads) {
            return [$existingFiles, [], []];
        }

        if ($categoryId === null) {
            throw ValidationException::withMessages([
                'event_category_id' => 'Kategori pendaftaran wajib dipilih untuk mengunggah dokumen.',
            ]);
        }

        /** @var EventCategory|null $category */
        $category = EventCategory::query()
            ->where('id', $categoryId)
            ->where('event_id', $eventId)
            ->first();

        if (! $category) {
            throw ValidationException::withMessages([
                'event_category_id' => 'Kategori pendaftaran tidak ditemukan atau tidak sesuai turnamen.',
            ]);
        }

        $community = $this->communityLookup->find($communityId);
        abort_unless($community?->is_active, 403, 'Entitas pendaftar tidak aktif.');
        $actualSubtype = (string) $community->subtype;

        $requiredDocs = (array) ($category->required_documents ?? []);
        $docTypeModels = ! empty($requiredDocs)
            ? DocumentType::query()->whereIn('code', $requiredDocs)->get()->keyBy('code')
            : collect();

        $applicableTeamRequiredDocs = [];
        $applicableAthleteRequiredDocs = [];

        foreach ($requiredDocs as $docCode) {
            /** @var DocumentType|null $meta */
            $meta = $docTypeModels->get($docCode);
            if (! $meta) {
                continue;
            }
            if (! in_array($meta->scope, ['team', 'athlete'], true)) {
                continue;
            }
            if (! empty($actualSubtype) && ! $meta->appliesToEntity($actualSubtype)) {
                continue;
            }
            if ($meta->scope === 'team') {
                $applicableTeamRequiredDocs[] = $docCode;
            } else {
                $applicableAthleteRequiredDocs[] = $docCode;
            }
        }

        // BATCH PRE-VALIDATION BEFORE ANY FILE STORAGE WRITES
        // 1. Team single document pre-validation
        if ($request->hasFile('team_document')) {
            $file = $request->file('team_document');
            if ($file instanceof UploadedFile) {
                if ($file->getSize() > 5 * 1024 * 1024) {
                    throw ValidationException::withMessages([
                        'team_document' => 'Ukuran berkas tidak boleh melebihi 5 MB.',
                    ]);
                }
                if (count($applicableTeamRequiredDocs) === 0) {
                    throw ValidationException::withMessages([
                        'team_document' => 'Unggahan dokumen tim ditolak: kategori ini tidak mewajibkan dokumen tim.',
                    ]);
                }
                if (count($applicableTeamRequiredDocs) > 1) {
                    throw ValidationException::withMessages([
                        'team_documents' => 'Unggahan dokumen tim ditolak: kategori mewajibkan beberapa dokumen tim secara terpisah.',
                    ]);
                }
            }
        }

        // 2. Team multiple documents pre-validation
        $teamUploads = $request->file('team_documents') ?? [];
        if (is_array($teamUploads)) {
            foreach ($teamUploads as $docCode => $file) {
                if ($file instanceof UploadedFile) {
                    if ($file->getSize() > 5 * 1024 * 1024) {
                        throw ValidationException::withMessages([
                            'team_documents' => 'Ukuran berkas tidak boleh melebihi 5 MB.',
                        ]);
                    }
                    if (! in_array((string) $docCode, $applicableTeamRequiredDocs, true)) {
                        throw ValidationException::withMessages([
                            'team_documents' => "Unggahan dokumen tim ditolak: jenis dokumen [{$docCode}] tidak berlaku atau bukan dokumen tim yang diwajibkan.",
                        ]);
                    }
                }
            }
        }

        // 3. Personnel licenses pre-validation
        $personnelUploads = $request->file('personnel_licenses');
        if (is_array($personnelUploads)) {
            $allowedRoles = collect(app(TeamPersonnelProvider::class)->roles())
                ->whereIn('code', ['coach', 'assistant_coach'])
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $selectedPersonnelKeys = collect($payload['personnel'] ?? [])
                ->map(fn ($p): string => ((int) ($p['user_id'] ?? 0)).':'.((int) ($p['role_id'] ?? 0)))
                ->all();

            foreach ($personnelUploads as $rawUid => $roles) {
                $uid = (int) $rawUid;
                if (! is_array($roles)) {
                    continue;
                }
                foreach ($roles as $rawRid => $file) {
                    $rid = (int) $rawRid;
                    if ($file instanceof UploadedFile) {
                        if (! in_array($rid, $allowedRoles, true)) {
                            throw ValidationException::withMessages([
                                'personnel_licenses' => 'Unggahan lisensi hanya diperbolehkan untuk peran Coach dan Assistant Coach.',
                            ]);
                        }
                        if (! in_array("{$uid}:{$rid}", $selectedPersonnelKeys, true)) {
                            throw ValidationException::withMessages([
                                'personnel_licenses' => 'Unggahan lisensi ditolak: pengguna tidak terdaftar dalam peran personel tersebut.',
                            ]);
                        }
                        if ($file->getSize() > 1024 * 1024) {
                            throw ValidationException::withMessages([
                                'personnel_licenses' => 'Ukuran gambar lisensi tidak boleh melebihi 1 MB.',
                            ]);
                        }
                        $mime = $file->getMimeType();
                        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                            throw ValidationException::withMessages([
                                'personnel_licenses' => 'Format lisensi harus berupa gambar (JPG, PNG, atau WebP).',
                            ]);
                        }
                    }
                }
            }
        }

        // 4. Athlete documents pre-validation
        $athleteUploads = $request->file('athlete_documents') ?? [];
        if (is_array($athleteUploads) && count($athleteUploads) > 0) {
            $selectedUserIds = [];
            if (! empty($payload['members']) && is_array($payload['members'])) {
                foreach ($payload['members'] as $m) {
                    if (isset($m['user_id'])) {
                        $selectedUserIds[] = (int) $m['user_id'];
                    }
                }
            }

            $eligibleUserIds = $this->rosterProvider->eligibleAthletes($communityId, $categoryId)
                ->pluck('userId')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($athleteUploads as $rawUid => $docs) {
                $uid = (int) $rawUid;

                $isAuthorized = in_array($uid, $selectedUserIds, true)
                    && (in_array($uid, $eligibleUserIds, true)
                        || $this->rosterProvider->isCandidateForDraft($draftId, $communityId, $uid));

                if (! $isAuthorized) {
                    throw ValidationException::withMessages([
                        'athlete_documents' => "Unggahan dokumen atlet ditolak: ID atlet [{$rawUid}] tidak terdaftar dalam roster atau tidak berwenang.",
                    ]);
                }

                if (is_array($docs)) {
                    foreach ($docs as $docCode => $file) {
                        if ($file instanceof UploadedFile) {
                            if ($file->getSize() > 5 * 1024 * 1024) {
                                throw ValidationException::withMessages([
                                    'athlete_documents' => 'Ukuran berkas tidak boleh melebihi 5 MB.',
                                ]);
                            }
                            if (! in_array((string) $docCode, $applicableAthleteRequiredDocs, true)) {
                                throw ValidationException::withMessages([
                                    'athlete_documents' => "Unggahan dokumen atlet ditolak: jenis dokumen [{$docCode}] tidak berlaku atau bukan dokumen atlet yang diwajibkan.",
                                ]);
                            }
                        }
                    }
                }
            }
        }

        // WRITES: ALL VALIDATED, PERFORM STORAGE
        $newlyUploadedPaths = [];
        $supersededPaths = [];

        // 1. Team single document storage
        if ($request->hasFile('team_document')) {
            $file = $request->file('team_document');
            if ($file instanceof UploadedFile && count($applicableTeamRequiredDocs) === 1) {
                $code = $applicableTeamRequiredDocs[0];
                if (isset($existingFiles['team_documents'][$code]['staged_path'])) {
                    $supersededPaths[] = $existingFiles['team_documents'][$code]['staged_path'];
                }
                $path = $file->store("registration_drafts/{$draftId}/team", 'local');
                $newlyUploadedPaths[] = $path;
                $existingFiles['team_documents'][$code] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                    'staged_path' => $path,
                    'field_key' => 'team_document',
                ];
            }
        }

        // 2. Team multiple documents storage
        if (is_array($teamUploads)) {
            foreach ($teamUploads as $code => $file) {
                if ($file instanceof UploadedFile) {
                    $codeStr = (string) $code;
                    if (isset($existingFiles['team_documents'][$codeStr]['staged_path'])) {
                        $supersededPaths[] = $existingFiles['team_documents'][$codeStr]['staged_path'];
                    }
                    $path = $file->store("registration_drafts/{$draftId}/team", 'local');
                    $newlyUploadedPaths[] = $path;
                    $existingFiles['team_documents'][$codeStr] = [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime' => $file->getMimeType(),
                        'staged_path' => $path,
                        'field_key' => "team_documents.{$codeStr}",
                    ];
                }
            }
        }

        // 3. Athlete documents storage
        if (is_array($athleteUploads)) {
            foreach ($athleteUploads as $rawUid => $docs) {
                $uid = (int) $rawUid;
                if (is_array($docs)) {
                    foreach ($docs as $code => $file) {
                        if ($file instanceof UploadedFile) {
                            $codeStr = (string) $code;
                            if (isset($existingFiles['athlete_documents'][$uid][$codeStr]['staged_path'])) {
                                $supersededPaths[] = $existingFiles['athlete_documents'][$uid][$codeStr]['staged_path'];
                            }
                            $path = $file->store("registration_drafts/{$draftId}/athlete", 'local');
                            $newlyUploadedPaths[] = $path;
                            if (! isset($existingFiles['athlete_documents'][$uid])) {
                                $existingFiles['athlete_documents'][$uid] = [];
                            }
                            $existingFiles['athlete_documents'][$uid][$codeStr] = [
                                'name' => $file->getClientOriginalName(),
                                'size' => $file->getSize(),
                                'mime' => $file->getMimeType(),
                                'staged_path' => $path,
                                'field_key' => "athlete_documents.{$uid}.{$codeStr}",
                            ];
                        }
                    }
                }
            }
        }

        // 4. Personnel licenses storage
        if (is_array($personnelUploads)) {
            foreach ($personnelUploads as $rawUid => $roles) {
                $uid = (int) $rawUid;
                if (is_array($roles)) {
                    foreach ($roles as $rawRid => $file) {
                        $rid = (int) $rawRid;
                        if ($file instanceof UploadedFile) {
                            if (isset($existingFiles['personnel_licenses'][$uid][$rid]['staged_path'])) {
                                $supersededPaths[] = $existingFiles['personnel_licenses'][$uid][$rid]['staged_path'];
                            }
                            $path = $file->store("registration_drafts/{$draftId}/personnel", 'local');
                            $newlyUploadedPaths[] = $path;
                            if (! isset($existingFiles['personnel_licenses'][$uid])) {
                                $existingFiles['personnel_licenses'][$uid] = [];
                            }
                            $existingFiles['personnel_licenses'][$uid][$rid] = [
                                'name' => $file->getClientOriginalName(),
                                'size' => $file->getSize(),
                                'mime' => $file->getMimeType(),
                                'staged_path' => $path,
                                'field_key' => "personnel_licenses.{$uid}.{$rid}",
                            ];
                        }
                    }
                }
            }
        }

        return [$existingFiles, $newlyUploadedPaths, $supersededPaths];
    }

    /**
     * @param  array<string, mixed>  $files
     * @param  list<string>  $clearKeys
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function stageClearFiles(string $draftId, array $files, array $clearKeys): array
    {
        $pendingDeletions = [];

        foreach ($clearKeys as $key) {
            if ($key === 'team_document' || $key === 'team_documents.general_team_document') {
                foreach ($files['team_documents'] ?? [] as $c => $docMeta) {
                    if (($docMeta['field_key'] ?? '') === 'team_document' || $c === 'general_team_document') {
                        $stagedPath = $docMeta['staged_path'] ?? null;
                        if ($stagedPath && str_starts_with($stagedPath, "registration_drafts/{$draftId}/")) {
                            $pendingDeletions[] = $stagedPath;
                        }
                        unset($files['team_documents'][$c]);
                    }
                }
            } elseif (str_starts_with($key, 'team_documents.')) {
                $code = substr($key, strlen('team_documents.'));
                if (isset($files['team_documents'][$code])) {
                    $stagedPath = $files['team_documents'][$code]['staged_path'] ?? null;
                    if ($stagedPath && str_starts_with($stagedPath, "registration_drafts/{$draftId}/")) {
                        $pendingDeletions[] = $stagedPath;
                    }
                    unset($files['team_documents'][$code]);
                }
            } elseif (str_starts_with($key, 'athlete_documents.')) {
                $parts = explode('.', $key);
                if (count($parts) === 3) {
                    $uid = (int) $parts[1];
                    $code = $parts[2];
                    if (isset($files['athlete_documents'][$uid][$code])) {
                        $stagedPath = $files['athlete_documents'][$uid][$code]['staged_path'] ?? null;
                        if ($stagedPath && str_starts_with($stagedPath, "registration_drafts/{$draftId}/")) {
                            $pendingDeletions[] = $stagedPath;
                        }
                        unset($files['athlete_documents'][$uid][$code]);
                        if (empty($files['athlete_documents'][$uid])) {
                            unset($files['athlete_documents'][$uid]);
                        }
                    }
                }
            } elseif (str_starts_with($key, 'personnel_licenses.')) {
                $parts = explode('.', $key);
                if (count($parts) === 3) {
                    $uid = (int) $parts[1];
                    $rid = (int) $parts[2];
                    if (isset($files['personnel_licenses'][$uid][$rid])) {
                        $stagedPath = $files['personnel_licenses'][$uid][$rid]['staged_path'] ?? null;
                        if ($stagedPath && str_starts_with($stagedPath, "registration_drafts/{$draftId}/")) {
                            $pendingDeletions[] = $stagedPath;
                        }
                        unset($files['personnel_licenses'][$uid][$rid]);
                        if (empty($files['personnel_licenses'][$uid])) {
                            unset($files['personnel_licenses'][$uid]);
                        }
                    }
                }
            }
        }

        return [$files, $pendingDeletions];
    }

    public function assertCategoryEntityCapacityNotExceeded(EventCategory $category, int $communityId, ?string $ignoringDraftId = null): void
    {
        $community = $this->communityLookup->find($communityId);
        if (! $community || ! $category->acceptsCommunityType($community->subtype, $community->institution_type, $community->school_level)) {
            throw ValidationException::withMessages(['event_category_id' => 'Kategori tidak sesuai dengan sub-tipe atau jenjang komunitas aktif.']);
        }
        $maxAllowed = max(1, (int) ($category->max_teams_per_entity ?? 1));

        $activeParticipantsCount = $this->registrationGuard
            ->activeEntityParticipants($category->participants(), now())
            ->where('community_id', $communityId)
            ->count();

        $activeDraftsCount = RegistrationDraft::query()
            ->where('event_category_id', $category->id)
            ->where('community_id', $communityId)
            ->whereNull('participant_id')
            ->where('expires_at', '>', now())
            ->when($ignoringDraftId, fn ($q) => $q->where('id', '!=', $ignoringDraftId))
            ->count();

        if (($activeParticipantsCount + $activeDraftsCount) >= $maxAllowed) {
            $msg = $maxAllowed === 1
                ? 'Batas maksimal tim per komunitas (1 tim) untuk kategori ini sudah tercapai.'
                : "Batas maksimal tim per komunitas ({$maxAllowed} tim) untuk kategori ini sudah tercapai.";

            throw ValidationException::withMessages([
                'event_category_id' => $msg,
            ]);
        }
    }
}
