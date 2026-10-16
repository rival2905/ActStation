<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\CommunityLookup;
use App\Models\User;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Exceptions\RosterValidationException;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RegistrationRosterRevisionService
{
    public function __construct(
        private readonly RosterValidator $rosterValidator,
        private readonly CommunityLookup $communityLookup,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @param  array<string, UploadedFile>  $teamDocuments
     * @param  array<int, array<string, UploadedFile>>  $athleteDocuments
     */
    public function resubmit(
        EventParticipant $participant,
        array $members,
        array $teamDocuments = [],
        array $athleteDocuments = [],
        ?User $actor = null,
        ?int $activeCommunityId = null,
        ?array $personnel = null
    ): EventParticipant {
        $newlyStoredFiles = [];

        try {
            return DB::transaction(function () use ($participant, $members, $teamDocuments, $athleteDocuments, $actor, $activeCommunityId, $personnel, &$newlyStoredFiles): EventParticipant {
                /** @var EventCategory $lockedCategory */
                $lockedCategory = EventCategory::query()
                    ->where('id', $participant->event_category_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                /** @var EventParticipant $lockedParticipant */
                $lockedParticipant = EventParticipant::query()
                    ->where('id', $participant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_if($lockedParticipant->status !== 'revision_needed', 403, 'Pendaftaran ini tidak dalam status revisi.');

                if ($actor !== null && ! $actor->hasRole('superadmin')) {
                    $userCommunity = $this->communityLookup->findForUser((int) $actor->id);
                    abort_unless(
                        $userCommunity !== null
                        && (int) $userCommunity->id === (int) $lockedParticipant->community_id
                        && ($activeCommunityId === null || $activeCommunityId === (int) $lockedParticipant->community_id),
                        403,
                        'Otorisasi manajer komunitas tidak valid atau telah dicabut.'
                    );
                }

                $lockedParticipant->setRelation('category', $lockedCategory);

                $this->rosterValidator->validate($lockedParticipant, $members);
                $selection = $personnel ?? $lockedParticipant->personnel()->get(['user_id', 'role_id'])->toArray();
                app(TeamPersonnelRoster::class)->sync($lockedParticipant, $selection);

                $community = $this->communityLookup->find((int) $lockedParticipant->community_id);
                $communitySubtype = $community?->subtype ?? 'free_association';

                $requiredDocs = (array) ($lockedCategory->required_documents ?? []);
                $docTypeModels = DocumentType::query()
                    ->whereIn('code', $requiredDocs)
                    ->get()
                    ->keyBy('code');

                $applicableTeamRequiredDocs = [];
                $applicableAthleteRequiredDocs = [];

                foreach ($requiredDocs as $docCode) {
                    $meta = $docTypeModels->get($docCode);
                    if (! $meta || ! in_array($meta->scope, ['team', 'athlete'], true)) {
                        throw new RosterValidationException("Metadata dokumen [{$docCode}] tidak valid.");
                    }

                    if (! empty($communitySubtype) && ! $meta->appliesToEntity($communitySubtype)) {
                        continue;
                    }

                    if ($meta->scope === 'team') {
                        $applicableTeamRequiredDocs[] = $docCode;
                    } else {
                        $applicableAthleteRequiredDocs[] = $docCode;
                    }
                }

                foreach (array_keys($teamDocuments) as $code) {
                    if (! in_array($code, $applicableTeamRequiredDocs, true)) {
                        throw new RosterValidationException("Unggahan dokumen tim ditolak: jenis dokumen [{$code}] tidak berlaku atau bukan dokumen tim yang diwajibkan.");
                    }
                    if (! ($teamDocuments[$code] instanceof UploadedFile)) {
                        throw new RosterValidationException("Berkas dokumen tim [{$code}] tidak valid.");
                    }
                }

                $selectedUserIds = array_map(fn ($m) => (int) $m['user_id'], $members);

                foreach ($athleteDocuments as $rawUid => $docs) {
                    $uid = (int) $rawUid;
                    if (! in_array($uid, $selectedUserIds, true)) {
                        throw new RosterValidationException("Unggahan dokumen atlet ditolak: ID atlet [{$rawUid}] tidak terdaftar dalam roster tim.");
                    }

                    if (! is_array($docs)) {
                        throw new RosterValidationException("Format unggahan dokumen atlet [{$rawUid}] tidak valid.");
                    }

                    foreach ($docs as $code => $file) {
                        if (! in_array($code, $applicableAthleteRequiredDocs, true)) {
                            throw new RosterValidationException("Unggahan dokumen atlet ditolak: jenis dokumen [{$code}] tidak berlaku atau bukan dokumen atlet yang diwajibkan.");
                        }
                        if (! ($file instanceof UploadedFile)) {
                            throw new RosterValidationException("Berkas dokumen atlet [{$code}] untuk atlet [{$uid}] tidak valid.");
                        }
                    }
                }

                foreach ($applicableTeamRequiredDocs as $docCode) {
                    $hasUpload = isset($teamDocuments[$docCode]) && $teamDocuments[$docCode] instanceof UploadedFile;
                    if (! $hasUpload) {
                        $existingTeamDoc = Document::query()
                            ->where('documentable_type', EventParticipant::class)
                            ->where('documentable_id', $lockedParticipant->id)
                            ->where('category', $docCode)
                            ->where('status', '!=', 'rejected')
                            ->first();

                        $hasExisting = $existingTeamDoc
                            && ! empty($existingTeamDoc->file_path)
                            && (Storage::disk('local')->exists($existingTeamDoc->file_path) || Storage::disk('public')->exists($existingTeamDoc->file_path));

                        if (! $hasExisting) {
                            $docName = $docTypeModels->get($docCode)?->name ?? $docCode;
                            throw new RosterValidationException("Dokumen tim [{$docName}] wajib dilampirkan atau tersedia berkas sah.");
                        }
                    }
                }

                foreach ($applicableAthleteRequiredDocs as $docCode) {
                    $docName = $docTypeModels->get($docCode)?->name ?? $docCode;
                    $existingVaultDocs = Document::query()
                        ->where('documentable_type', User::class)
                        ->whereIn('documentable_id', $selectedUserIds)
                        ->where('category', $docCode)
                        ->where('status', '!=', 'rejected')
                        ->get()
                        ->filter(fn (Document $doc) => ! empty($doc->file_path) && (Storage::disk('local')->exists($doc->file_path) || Storage::disk('public')->exists($doc->file_path)))
                        ->keyBy('documentable_id');

                    foreach ($members as $m) {
                        $uid = (int) $m['user_id'];
                        $hasUpload = isset($athleteDocuments[$uid][$docCode]) && $athleteDocuments[$uid][$docCode] instanceof UploadedFile;
                        $hasExisting = isset($existingVaultDocs[$uid]);

                        if (! $hasUpload && ! $hasExisting) {
                            $jersey = $m['jersey_number'] ?? $uid;
                            throw new RosterValidationException("Dokumen [{$docName}] untuk pemain #{$jersey} belum tersedia di Digital Vault dan belum diunggah.");
                        }
                    }
                }

                foreach ($teamDocuments as $code => $file) {
                    if ($file instanceof UploadedFile) {
                        $path = $file->store('participant_documents', 'local');
                        $newlyStoredFiles[] = $path;
                        $docName = $docTypeModels->get($code)?->name ?? ucfirst(str_replace('_', ' ', (string) $code));

                        $existingDoc = Document::query()
                            ->where('documentable_type', EventParticipant::class)
                            ->where('documentable_id', $lockedParticipant->id)
                            ->where('category', (string) $code)
                            ->first();

                        if ($existingDoc) {
                            $existingDoc->update([
                                'name' => $docName,
                                'file_path' => $path,
                                'status' => 'pending',
                                'verified_at' => null,
                                'verified_by' => null,
                                'is_public' => false,
                            ]);
                        } else {
                            Document::query()->create([
                                'documentable_type' => EventParticipant::class,
                                'documentable_id' => $lockedParticipant->id,
                                'category' => (string) $code,
                                'name' => $docName,
                                'file_path' => $path,
                                'status' => 'pending',
                                'verified_at' => null,
                                'verified_by' => null,
                                'is_public' => false,
                            ]);
                        }
                    }
                }

                foreach ($athleteDocuments as $userId => $docs) {
                    if (! is_array($docs)) {
                        continue;
                    }

                    foreach ($docs as $code => $file) {
                        if ($file instanceof UploadedFile) {
                            $path = $file->store('vault_documents', 'local');
                            $newlyStoredFiles[] = $path;
                            $docName = $docTypeModels->get($code)?->name ?? ucfirst(str_replace('_', ' ', (string) $code));

                            $existingDoc = Document::query()
                                ->where('documentable_type', User::class)
                                ->where('documentable_id', (int) $userId)
                                ->where('category', (string) $code)
                                ->first();

                            if ($existingDoc) {
                                $existingDoc->update([
                                    'name' => $docName,
                                    'file_path' => $path,
                                    'status' => 'pending',
                                    'verified_at' => null,
                                    'verified_by' => null,
                                    'is_public' => false,
                                ]);
                            } else {
                                Document::query()->create([
                                    'documentable_type' => User::class,
                                    'documentable_id' => (int) $userId,
                                    'category' => (string) $code,
                                    'name' => $docName,
                                    'file_path' => $path,
                                    'status' => 'pending',
                                    'verified_at' => null,
                                    'verified_by' => null,
                                    'is_public' => false,
                                ]);
                            }
                        }
                    }
                }

                $lockedParticipant->members()->delete();

                foreach ($members as $memberData) {
                    $lockedParticipant->members()->create([
                        'user_id' => (int) $memberData['user_id'],
                        'team_role' => $memberData['team_role'] ?? 'player',
                        'jersey_number' => isset($memberData['jersey_number']) && $memberData['jersey_number'] !== ''
                            ? (int) $memberData['jersey_number']
                            : null,
                        'is_captain' => ! empty($memberData['is_captain']),
                        'position' => $memberData['position'] ?? null,
                    ]);
                }

                $fee = (int) ($lockedCategory->registration_fee ?? 0);
                $flow = (string) $lockedCategory->registration_flow;

                if ($fee <= 0 || $flow === 'screening_first' || $flow === 'instant_free') {
                    $nextStatus = 'registered';
                } else {
                    $isPaid = Transaction::query()
                        ->where('payable_type', 'event_participant')
                        ->where('payable_id', $lockedParticipant->id)
                        ->where('status', 'paid')
                        ->exists();

                    $nextStatus = $isPaid ? 'registered' : 'pending_payment';
                }

                $lockedParticipant->update([
                    'status' => $nextStatus,
                    'revision_notes' => null,
                ]);

                return $lockedParticipant;
            });
        } catch (\Throwable $e) {
            foreach ($newlyStoredFiles as $filePath) {
                Storage::disk('local')->delete($filePath);
            }

            throw $e;
        }
    }
}
