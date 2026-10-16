<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\CommunityLookup;
use App\Contracts\RosterProvider;
use App\Models\User;
use App\Modules\Event\Exceptions\RosterValidationException;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Support\Facades\Storage;

class RegistrationInspectionSummary
{
    public function __construct(
        private readonly RosterProvider $rosterProvider,
        private readonly CommunityLookup $communityLookup,
        private readonly RosterValidator $rosterValidator,
    ) {}

    /**
     * Inspect participant roster, category constraints, and document completeness.
     *
     * @return array{
     *     per_user: array<int, array{name: string, label: string, reasons: list<string>, documents: array<string, array{name: string, status: string, exists: bool, notes: ?string}>}>,
     *     team_documents: array<string, array{name: string, status: string, exists: bool, notes: ?string}>,
     *     team_complete: bool
     * }
     */
    public function inspect(EventParticipant $participant): array
    {
        $category = $participant->category ?? EventCategory::query()->find($participant->event_category_id);
        $members = $participant->members ?? collect();
        $members->loadMissing('user');

        $communityId = (int) ($participant->community_id ?? 0);
        $community = $communityId > 0 ? $this->communityLookup->find($communityId) : null;
        $subtype = $community?->subtype ?? 'club';

        $requiredCodes = (array) ($category?->required_documents ?? []);
        $docTypes = ! empty($requiredCodes)
            ? DocumentType::query()->whereIn('code', $requiredCodes)->get()->keyBy('code')
            : collect();

        // Inferred canonical Document model from User::documents() without cross-module import
        $docClass = (new User)->documents()->getRelated();

        $participantDocs = $docClass->newQuery()
            ->where('documentable_type', EventParticipant::class)
            ->where('documentable_id', $participant->id)
            ->get()
            ->keyBy('category');

        $userIds = $members->pluck('user_id')->filter()->all();
        $userDocs = ! empty($userIds)
            ? $docClass->newQuery()
                ->where('documentable_type', User::class)
                ->whereIn('documentable_id', $userIds)
                ->get()
                ->groupBy('documentable_id')
            : collect();

        $teamDocsSummary = [];
        $teamDocsComplete = true;

        foreach ($requiredCodes as $code) {
            $docType = $docTypes->get($code);
            if (! $docType) {
                // Unknown type failclosed
                $teamDocsSummary[$code] = [
                    'name' => ucfirst(str_replace('_', ' ', (string) $code)),
                    'status' => 'missing',
                    'exists' => false,
                    'notes' => 'Tipe berkas tidak dikenali sistem.',
                ];
                $teamDocsComplete = false;

                continue;
            }

            if (! $docType->appliesToEntity($subtype) || $docType->scope !== 'team') {
                continue;
            }

            $doc = $participantDocs->get($code);
            $filePath = $doc?->file_path;
            $exists = ! empty($filePath) && Storage::disk('local')->exists($filePath);
            $status = $exists ? ($doc->status ?? 'pending') : 'missing';

            if (! $exists || $status === 'rejected') {
                $teamDocsComplete = false;
            }

            $teamDocsSummary[$code] = [
                'name' => $docType->name,
                'status' => $status,
                'exists' => $exists,
                'notes' => $doc?->notes,
            ];
        }

        // Roster validation check
        $membersPayload = [];
        foreach ($members as $m) {
            $membersPayload[] = [
                'user_id' => $m->user_id,
                'jersey_number' => $m->jersey_number,
                'is_captain' => (bool) $m->is_captain,
                'position' => $m->position,
                'team_role' => $m->team_role ?? 'player',
            ];
        }

        $validationErrors = [];
        try {
            $this->rosterValidator->validate($participant, $membersPayload);
        } catch (RosterValidationException $e) {
            $validationErrors = $e->errors;
        }

        $eligibleMap = collect();
        if ($communityId > 0 && $category) {
            $eligibleMap = $this->rosterProvider->eligibleAthletes($communityId, (int) $category->id)->keyBy('userId');
        }

        $perUser = [];

        foreach ($members as $idx => $m) {
            $uid = (int) $m->user_id;
            $user = $m->user;
            $userName = $user?->name ?? "User #{$uid}";
            $reasons = [];

            // Membership / Eligibility check
            if ($communityId > 0 && ! $eligibleMap->has($uid)) {
                $reasons[] = 'Atlet bukan anggota komunitas aktif atau akunnya nonaktif.';
            }

            // Map RosterValidator specific errors
            foreach ($validationErrors as $key => $errList) {
                if ($key === "members.{$idx}.user_id" || $key === "members.{$idx}.gender" || $key === "members.{$idx}.age" || $key === "members.{$idx}.birth_date") {
                    foreach ((array) $errList as $errMsg) {
                        $reasons[] = $errMsg;
                    }
                }
            }

            $userDocsForUid = ($userDocs->get($uid) ?? collect())->keyBy('category');
            $athleteDocsSummary = [];
            $hasMissing = false;
            $hasRejected = false;
            $allVerified = true;
            $requiredAthleteDocCount = 0;

            foreach ($requiredCodes as $code) {
                $docType = $docTypes->get($code);
                if (! $docType) {
                    $athleteDocsSummary[$code] = [
                        'name' => ucfirst(str_replace('_', ' ', (string) $code)),
                        'status' => 'missing',
                        'exists' => false,
                        'notes' => 'Tipe berkas tidak dikenali.',
                    ];
                    $hasMissing = true;
                    $allVerified = false;
                    $reasons[] = "Berkas [{$code}] belum terdaftar dalam sistem.";

                    continue;
                }

                if (! $docType->appliesToEntity($subtype) || $docType->scope !== 'athlete') {
                    continue;
                }

                $requiredAthleteDocCount++;
                $doc = $userDocsForUid->get($code);
                $filePath = $doc?->file_path;
                $exists = ! empty($filePath) && Storage::disk('local')->exists($filePath);
                $status = $exists ? ($doc->status ?? 'pending') : 'missing';

                if (! $exists) {
                    $hasMissing = true;
                    $allVerified = false;
                    $reasons[] = "Berkas {$docType->name} belum diunggah.";
                } elseif ($status === 'rejected') {
                    $hasRejected = true;
                    $allVerified = false;
                    $reasons[] = "Berkas {$docType->name} ditolak: ".($doc->notes ?? 'Perlu perbaikan.');
                } elseif ($status !== 'verified') {
                    $allVerified = false;
                }

                $athleteDocsSummary[$code] = [
                    'name' => $docType->name,
                    'status' => $status,
                    'exists' => $exists,
                    'notes' => $doc?->notes,
                ];
            }

            // Determine label
            if ($hasRejected || ! empty($reasons)) {
                $label = 'Perlu Perbaikan';
            } elseif ($hasMissing) {
                $label = 'Belum Lengkap';
            } elseif ($allVerified && $requiredAthleteDocCount > 0 && empty($reasons)) {
                $label = 'Memenuhi Syarat';
            } else {
                $label = 'Menunggu Pemeriksaan';
            }

            $perUser[$uid] = [
                'name' => $userName,
                'label' => $label,
                'reasons' => array_values(array_unique($reasons)),
                'documents' => $athleteDocsSummary,
            ];
        }

        $minRoster = $category?->min_roster ?? 0;
        $maxRoster = $category?->max_roster ?? PHP_INT_MAX;
        $rosterCount = count($membersPayload);
        $rosterCountValid = $rosterCount >= $minRoster && $rosterCount <= $maxRoster;

        $athletesComplete = collect($perUser)->every(fn (array $athlete): bool => empty($athlete['reasons'])
            && collect($athlete['documents'])->every(fn (array $document): bool => $document['exists'] && $document['status'] !== 'rejected'));
        $teamComplete = $teamDocsComplete && $rosterCountValid && empty($validationErrors) && $athletesComplete;

        return [
            'per_user' => $perUser,
            'team_documents' => $teamDocsSummary,
            'team_complete' => $teamComplete,
        ];
    }
}
