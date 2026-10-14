<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\CommunityLookup;
use App\Contracts\DTOs\CommunitySummary;
use App\Contracts\EntityRequirements;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunityLookupService implements CommunityLookup
{
    /**
     * @var array<string, list<string>>
     */
    private const CODE_ALIASES = [
        'legal_entity_proof' => ['legal_entity_proof', 'deed'],
        'institution_decree' => ['institution_decree', 'decree'],
    ];

    public function find(int $id): ?CommunitySummary
    {
        $community = Community::find($id);

        if (! $community) {
            return null;
        }

        return new CommunitySummary(
            id: (int) $community->id,
            name: (string) $community->name,
            slug: (string) $community->slug,
            status: (string) $community->status,
            subtype: (string) $community->subtype,
            sport_category_id: $community->sport_category_id !== null ? (int) $community->sport_category_id : null,
            city_id: $community->city_id !== null ? (string) $community->city_id : null,
            institution_type: $community->institution_type !== null ? (string) $community->institution_type : null,
            is_active: (bool) $community->is_active,
            school_level: $community->subtype === 'school' && $community->npsn
                ? DB::table('schools')->where('npsn', $community->npsn)->value('school_level')
                : null,
            latitude: $community->latitude !== null ? (float) $community->latitude : null,
            longitude: $community->longitude !== null ? (float) $community->longitude : null,
        );
    }

    public static function resolveActiveCommunityId(?int $userId = null): ?int
    {
        $userId = $userId ?? (int) auth()->id();
        if (! $userId) {
            return null;
        }

        $sessionCommunityId = (int) auth()->id() === $userId ? session('current_community_id') : null;
        if ($sessionCommunityId) {
            $official = CommunityOfficial::query()
                ->where('user_id', $userId)
                ->where('community_id', (int) $sessionCommunityId)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->whereHas('community', fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))
                ->first();

            if ($official) {
                return (int) $official->community_id;
            }

            $authUser = auth()->user();
            if ($authUser && method_exists($authUser, 'hasRole') && ($authUser->hasRole('superadmin') || $authUser->hasRole('admin'))) {
                $communityExists = Community::query()
                    ->where('id', (int) $sessionCommunityId)
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->exists();

                if ($communityExists) {
                    return (int) $sessionCommunityId;
                }
            }

            session()->forget('current_community_id');
        }

        $firstOfficial = CommunityOfficial::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->whereHas('community', fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))
            ->orderBy('id')
            ->first();

        return $firstOfficial ? (int) $firstOfficial->community_id : null;
    }

    public function findForUser(int $userId): ?CommunitySummary
    {
        $authId = (int) auth()->id();

        if ($authId !== 0 && $authId === $userId) {
            $activeCommunityId = self::resolveActiveCommunityId($userId);
            if (! $activeCommunityId) {
                return null;
            }

            $summary = $this->find($activeCommunityId);

            return $summary?->is_active ? $summary : null;
        }

        $official = CommunityOfficial::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->whereHas('community', fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))
            ->orderBy('id')
            ->first();

        if (! $official) {
            return null;
        }

        $summary = $this->find((int) $official->community_id);

        return $summary?->is_active ? $summary : null;
    }

    /**
     * @return array<string, array{id: int, category: string, name: string}>
     */
    public function legalDocumentsForUser(int $userId, int $communityId): array
    {
        $community = Community::query()
            ->where('id', $communityId)
            ->where('is_active', true)
            ->where('status', 'verified')
            ->first();

        if (! $community) {
            return [];
        }

        $isActiveOfficial = CommunityOfficial::query()
            ->where('community_id', $communityId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->exists();

        if (! $isActiveOfficial) {
            return [];
        }

        $expectedCodes = array_keys(EntityRequirements::DOCUMENT_LABELS);
        if (empty($expectedCodes)) {
            return [];
        }

        $result = [];
        foreach ($expectedCodes as $code) {
            $categories = self::CODE_ALIASES[$code] ?? [$code];

            $document = $community->documents()
                ->whereIn('category', $categories)
                ->where('status', 'verified')
                ->where('is_public', false)
                ->latest()
                ->first();

            if ($document && $document->file_path && Storage::disk('local')->exists($document->file_path)) {
                $result[$code] = [
                    'id' => (int) $document->id,
                    'category' => (string) $document->category,
                    'name' => (string) $document->name,
                ];
            }
        }

        return $result;
    }

    public function legalDocumentDownloadForUser(int $userId, int $communityId, string $code): StreamedResponse
    {
        $community = Community::query()
            ->where('id', $communityId)
            ->where('is_active', true)
            ->where('status', 'verified')
            ->first();

        if (! $community) {
            abort(404, 'Komunitas tidak ditemukan.');
        }

        $isActiveOfficial = CommunityOfficial::query()
            ->where('community_id', $communityId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->exists();

        if (! $isActiveOfficial) {
            abort(403, 'Akses ditolak.');
        }

        $allowedCodes = array_keys(EntityRequirements::DOCUMENT_LABELS);
        if (! in_array($code, $allowedCodes, true)) {
            abort(404, 'Kode dokumen tidak valid.');
        }

        $categories = self::CODE_ALIASES[$code] ?? [$code];

        $document = $community->documents()
            ->whereIn('category', $categories)
            ->where('status', 'verified')
            ->where('is_public', false)
            ->latest()
            ->first();

        if (! $document) {
            abort(404, 'Dokumen tidak ditemukan.');
        }

        if (! $document->file_path || ! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File dokumen tidak ditemukan.');
        }

        return Storage::disk('local')->download($document->file_path, $document->name);
    }
}
