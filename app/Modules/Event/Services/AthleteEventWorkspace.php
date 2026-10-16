<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\AthleteDocumentVault;
use App\Contracts\CommunityLookup;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;

class AthleteEventWorkspace
{
    public function __construct(
        private readonly AthleteDocumentVault $vault,
        private readonly CommunityLookup $communityLookup,
    ) {}

    /**
     * @return array{participants: Collection<int, EventParticipant>, drafts: SupportCollection<int, RegistrationDraft>}
     */
    public function getWorkspaceData(int $userId): array
    {
        /** @var Collection<int, EventParticipant> $participants */
        $participants = EventParticipant::query()
            ->where(function ($q) use ($userId): void {
                $q->where('user_id', $userId)
                    ->orWhereHas('members', fn ($mq) => $mq->where('user_id', $userId));
            })
            ->with(['event', 'category'])
            ->latest('registered_at')
            ->get();

        foreach ($participants as $p) {
            $p->tasks = $this->getRequiredAthleteDocumentsForParticipant($p, $userId);
            $p->can_upload = ! in_array($p->status, ['confirmed', 'cancelled', 'rejected'], true);
        }

        $drafts = RegistrationDraft::query()
            ->whereNull('participant_id')
            ->unexpired()
            ->with(['event', 'category'])
            ->latest('created_at')
            ->get()
            ->filter(function (RegistrationDraft $draft) use ($userId): bool {
                if ($draft->isSubmitted() || $draft->isExpired()) {
                    return false;
                }
                if ($draft->event && $draft->event->status !== 'published') {
                    return false;
                }
                $payload = $draft->payload ?? [];
                $members = $payload['members'] ?? [];
                if (! is_array($members)) {
                    return false;
                }
                foreach ($members as $member) {
                    if (isset($member['user_id']) && (int) $member['user_id'] === $userId) {
                        return true;
                    }
                }

                return false;
            })
            ->values();

        foreach ($drafts as $draft) {
            $draft->tasks = $this->getRequiredAthleteDocumentsForDraft($draft, $userId);
            $draft->can_upload = ! $draft->isExpired() && ! $draft->isSubmitted() && $draft->event?->status === 'published';
        }

        return [
            'participants' => $participants,
            'drafts' => $drafts,
        ];
    }

    /**
     * @return list<array{code: string, name: string, exists: bool, status: ?string}>
     */
    public function getRequiredAthleteDocumentsForDraft(RegistrationDraft $draft, int $userId): array
    {
        $category = $draft->category;
        $requiredDocs = (array) ($category?->required_documents ?? []);
        if (empty($requiredDocs)) {
            return [];
        }

        $communityId = (int) $draft->community_id;
        $community = $communityId > 0 ? $this->communityLookup->find($communityId) : null;
        $subtype = $community?->subtype ?? 'club';

        $docTypeModels = DocumentType::query()
            ->whereIn('code', $requiredDocs)
            ->get()
            ->keyBy('code');

        $applicableDocs = [];
        foreach ($requiredDocs as $docCode) {
            /** @var DocumentType|null $meta */
            $meta = $docTypeModels->get($docCode);
            if (! $meta) {
                continue;
            }
            if ($meta->scope !== 'athlete') {
                continue;
            }
            if (! $meta->appliesToEntity($subtype)) {
                continue;
            }
            $applicableDocs[$docCode] = $meta;
        }

        if (empty($applicableDocs)) {
            return [];
        }

        $summaries = $this->vault->summaries($userId, array_keys($applicableDocs));

        $tasks = [];
        foreach ($applicableDocs as $code => $meta) {
            $summary = $summaries[$code] ?? ['exists' => false, 'status' => null];
            $tasks[] = [
                'code' => $code,
                'name' => $meta->name,
                'exists' => (bool) ($summary['exists'] ?? false),
                'status' => $summary['status'] ?? null,
            ];
        }

        return $tasks;
    }

    /**
     * @return list<array{code: string, name: string, exists: bool, status: ?string}>
     */
    public function getRequiredAthleteDocumentsForParticipant(EventParticipant $participant, int $userId): array
    {
        $category = $participant->category;
        $requiredDocs = (array) ($category?->required_documents ?? []);
        if (empty($requiredDocs)) {
            return [];
        }

        $communityId = (int) $participant->community_id;
        $community = $communityId > 0 ? $this->communityLookup->find($communityId) : null;
        $subtype = $community?->subtype ?? 'club';

        $docTypeModels = DocumentType::query()
            ->whereIn('code', $requiredDocs)
            ->get()
            ->keyBy('code');

        $applicableDocs = [];
        foreach ($requiredDocs as $docCode) {
            /** @var DocumentType|null $meta */
            $meta = $docTypeModels->get($docCode);
            if (! $meta) {
                continue;
            }
            if ($meta->scope !== 'athlete') {
                continue;
            }
            if (! $meta->appliesToEntity($subtype)) {
                continue;
            }
            $applicableDocs[$docCode] = $meta;
        }

        if (empty($applicableDocs)) {
            return [];
        }

        $summaries = $this->vault->summaries($userId, array_keys($applicableDocs));

        $tasks = [];
        foreach ($applicableDocs as $code => $meta) {
            $summary = $summaries[$code] ?? ['exists' => false, 'status' => null];
            $tasks[] = [
                'code' => $code,
                'name' => $meta->name,
                'exists' => (bool) ($summary['exists'] ?? false),
                'status' => $summary['status'] ?? null,
            ];
        }

        return $tasks;
    }

    public function uploadDocument(int $userId, string $kind, string $id, string $code, UploadedFile $file): void
    {
        abort_unless(in_array($kind, ['draft', 'participant'], true), 404, 'Tipe pendaftaran tidak valid.');

        if ($kind === 'draft') {
            /** @var RegistrationDraft $draft */
            $draft = RegistrationDraft::query()->where('id', $id)->firstOrFail();

            abort_if($draft->isExpired() || $draft->isSubmitted(), 422, 'Draft pendaftaran sudah kedaluwarsa atau telah dikirimkan.');
            abort_unless($draft->event?->status === 'published', 403, 'Turnamen tidak aktif.');

            $payload = $draft->payload ?? [];
            $members = $payload['members'] ?? [];
            $isMember = false;
            if (is_array($members)) {
                foreach ($members as $member) {
                    if (isset($member['user_id']) && (int) $member['user_id'] === $userId) {
                        $isMember = true;
                        break;
                    }
                }
            }
            abort_unless($isMember, 403, 'Anda bukan anggota tim dalam draft pendaftaran ini.');

            $category = $draft->category;
            $requiredDocs = (array) ($category?->required_documents ?? []);
            abort_unless(in_array($code, $requiredDocs, true), 422, 'Dokumen tidak disyaratkan oleh kategori ini.');

            $communityId = (int) $draft->community_id;
            $community = $communityId > 0 ? $this->communityLookup->find($communityId) : null;
            $subtype = $community?->subtype ?? 'club';

            /** @var DocumentType|null $docType */
            $docType = DocumentType::query()->where('code', $code)->first();
            abort_unless($docType && $docType->scope === 'athlete' && $docType->appliesToEntity($subtype), 422, 'Dokumen tidak valid untuk atlet.');

            $this->vault->upload($userId, $code, $file);

            return;
        }

        // participant
        /** @var EventParticipant $participant */
        $participant = EventParticipant::query()->where('id', $id)->firstOrFail();

        abort_if(in_array($participant->status, ['confirmed', 'cancelled', 'rejected'], true), 422, 'Status partisipasi tidak mengizinkan pengunggahan dokumen.');

        $isOwn = (int) $participant->user_id === $userId || $participant->members()->where('user_id', $userId)->exists();
        abort_unless($isOwn, 403, 'Anda tidak memiliki akses ke partisipasi ini.');

        $category = $participant->category;
        $requiredDocs = (array) ($category?->required_documents ?? []);
        abort_unless(in_array($code, $requiredDocs, true), 422, 'Dokumen tidak disyaratkan oleh kategori ini.');

        $communityId = (int) $participant->community_id;
        $community = $communityId > 0 ? $this->communityLookup->find($communityId) : null;
        $subtype = $community?->subtype ?? 'club';

        /** @var DocumentType|null $docType */
        $docType = DocumentType::query()->where('code', $code)->first();
        abort_unless($docType && $docType->scope === 'athlete' && $docType->appliesToEntity($subtype), 422, 'Dokumen tidak valid untuk atlet.');

        $this->vault->upload($userId, $code, $file);
    }
}
