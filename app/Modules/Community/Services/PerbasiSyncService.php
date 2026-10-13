<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

// ponytail: JSON file sync driver only; upgrade to HTTP driver (PERBASI_API_URL/PERBASI_API_KEY) once PERBASI source system publishes REST endpoints.
class PerbasiSyncService
{
    /**
     * Map subtype from PERBASI schema to ActStation subtype enum.
     */
    public function mapSubtype(?string $subtype): string
    {
        if ($subtype === null || $subtype === '') {
            return 'free_association';
        }

        $lower = strtolower($subtype);
        if (in_array($lower, ['school', 'school_sd', 'school_smp', 'school_sma'], true)) {
            return 'school';
        }

        if (in_array($lower, ['university', 'institution', 'club', 'free_association'], true)) {
            return $lower;
        }

        return 'club';
    }

    /**
     * Sync from a JSON file.
     *
     * @return array{communities_synced: int, athletes_synced: int, dry_run: bool}
     */
    public function syncFromFile(string $filePath, bool $dryRun = false): array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new InvalidArgumentException("File tidak ditemukan atau tidak dapat dibaca: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Gagal membaca file: {$filePath}");
        }

        $data = json_decode($content, true);
        if (! is_array($data)) {
            throw new InvalidArgumentException('Format JSON tidak valid.');
        }

        return $this->syncData($data, $dryRun);
    }

    /**
     * Sync payload array.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>  $payload
     * @return array{communities_synced: int, athletes_synced: int, dry_run: bool}
     */
    public function syncData(array $payload, bool $dryRun = false): array
    {
        $communitiesData = $payload['communities'] ?? (isset($payload[0]) ? $payload : []);
        $topAthletesData = $payload['athletes'] ?? [];

        $communitiesCount = 0;
        $athletesCount = 0;

        if ($dryRun) {
            $communitiesCount = count($communitiesData);
            foreach ($communitiesData as $cItem) {
                if (isset($cItem['athletes']) && is_array($cItem['athletes'])) {
                    $athletesCount += count($cItem['athletes']);
                }
            }
            if (is_array($topAthletesData)) {
                $athletesCount += count($topAthletesData);
            }

            return [
                'communities_synced' => $communitiesCount,
                'athletes_synced' => $athletesCount,
                'dry_run' => true,
            ];
        }

        DB::transaction(function () use ($communitiesData, $topAthletesData, &$communitiesCount, &$athletesCount): void {
            $now = now();

            foreach ($communitiesData as $cItem) {
                $community = $this->upsertCommunity($cItem, $now);
                $communitiesCount++;

                if (isset($cItem['athletes']) && is_array($cItem['athletes'])) {
                    foreach ($cItem['athletes'] as $aItem) {
                        $this->upsertAthlete($community, $aItem, $now);
                        $athletesCount++;
                    }
                }
            }

            if (is_array($topAthletesData)) {
                foreach ($topAthletesData as $aItem) {
                    $communityRef = $aItem['community_ref'] ?? null;
                    $community = null;
                    if ($communityRef) {
                        $community = Community::where('external_ref', $communityRef)->first();
                    }
                    if ($community) {
                        $this->upsertAthlete($community, $aItem, $now);
                        $athletesCount++;
                    }
                }
            }
        });

        return [
            'communities_synced' => $communitiesCount,
            'athletes_synced' => $athletesCount,
            'dry_run' => false,
        ];
    }

    /**
     * Upsert single community by external_ref.
     *
     * @param  array<string, mixed>  $data
     */
    protected function upsertCommunity(array $data, DateTimeInterface $now): Community
    {
        $externalRef = $data['external_ref'] ?? null;
        if (! $externalRef) {
            $externalRef = 'PERBASI-'.Str::slug((string) ($data['name'] ?? 'comm')).'-'.Str::random(6);
        }

        $community = Community::where('external_ref', $externalRef)->first();

        $name = (string) ($data['name'] ?? 'Komunitas PERBASI');
        $slug = $community?->slug ?? (Str::slug($name).'-'.Str::random(5));
        $subtype = $this->mapSubtype($data['subtype'] ?? null);

        $attributes = [
            'name' => $name,
            'subtype' => $subtype,
            'province_id' => (string) ($data['province_id'] ?? '32'),
            'city_id' => (string) ($data['city_id'] ?? '32.73'),
            'subdistrict_id' => $data['subdistrict_id'] ?? null,
            'village_id' => $data['village_id'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'address' => $data['address'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'npsn' => $data['npsn'] ?? null,
            'status' => $data['status'] ?? 'verified',
            'is_active' => true,
            'synced_at' => $now,
        ];

        // Do not overwrite local description and logo if already present
        if (! $community || empty($community->description)) {
            $attributes['description'] = $data['description'] ?? null;
        }
        if (! $community || empty($community->logo)) {
            $attributes['logo'] = $data['logo'] ?? null;
        }

        if ($community) {
            $community->update($attributes);

            return $community;
        }

        $attributes['external_ref'] = $externalRef;
        $attributes['slug'] = $slug;

        return Community::create($attributes);
    }

    /**
     * Upsert single athlete by users.external_ref with identity_number fallback.
     * Note: identity_number is used ONLY for matching, NEVER logged or printed.
     *
     * @param  array<string, mixed>  $data
     */
    protected function upsertAthlete(Community $community, array $data, DateTimeInterface $now): CommunityAthlete
    {
        $athleteRef = $data['external_ref'] ?? null;
        $identityNumber = isset($data['identity_number']) ? (string) $data['identity_number'] : null;

        $user = null;
        if ($athleteRef) {
            $user = User::where('external_ref', $athleteRef)->first();
        }

        if (! $user && ! empty($identityNumber)) {
            $user = User::where('identity_number', $identityNumber)->first();
        }

        if (! $user) {
            $userName = (string) ($data['name'] ?? 'Atlet PERBASI');
            $userEmail = $data['email'] ?? ($athleteRef ? "{$athleteRef}@perbasi.sync" : ('athlete-'.Str::random(10).'@perbasi.sync'));

            $user = User::create([
                'name' => $userName,
                'email' => $userEmail,
                'external_ref' => $athleteRef,
                'identity_number' => $identityNumber,
                'gender' => $data['gender'] ?? 'male',
                'birth_date' => $data['birth_date'] ?? null,
                'birth_place' => $data['birth_place'] ?? null,
                'is_active' => true,
                'password' => Hash::make(Str::random(16)),
            ]);
        } else {
            if ($athleteRef && empty($user->external_ref)) {
                $user->external_ref = $athleteRef;
                $user->save();
            }
        }

        $ca = null;
        if ($athleteRef) {
            $ca = CommunityAthlete::where('external_ref', $athleteRef)->first();
        }
        if (! $ca) {
            $ca = CommunityAthlete::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->first();
        }

        $caAttributes = [
            'external_ref' => $athleteRef ?? ($ca?->external_ref),
            'jersey_number' => isset($data['jersey_number']) ? (int) $data['jersey_number'] : ($ca?->jersey_number),
            'position' => $data['position'] ?? ($ca?->position),
            'status' => 'active',
            'region_status' => 'verified',
            'synced_at' => $now,
        ];

        if ($ca) {
            $ca->update($caAttributes);

            return $ca;
        }

        $caAttributes['community_id'] = $community->id;
        $caAttributes['user_id'] = $user->id;
        $caAttributes['initiated_by'] = 'community';
        $caAttributes['joined_at'] = $data['joined_at'] ?? now()->toDateString();

        return CommunityAthlete::create($caAttributes);
    }
}
