<?php

declare(strict_types=1);

namespace App\Modules\Report\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Pengecualian eksplisit: Report membaca lewat DB::table() read-only, TANPA model Eloquent modul lain.
class CoverageMapService
{
    /**
     * @return array<string, mixed>
     */
    public function getInitialSummary(): array
    {
        $totalVenues = DB::table('venues')->where('is_active', true)->count();
        $totalCommunities = DB::table('communities')->whereNull('deleted_at')->count();
        $verifiedCommunities = DB::table('communities')->whereNull('deleted_at')->where('status', 'verified')->count();
        $unverifiedCommunities = DB::table('communities')->whereNull('deleted_at')->where('status', '!=', 'verified')->count();
        $schoolsWithCoords = DB::table('schools')->whereNotNull('latitude')->whereNotNull('longitude')->count();
        $schoolsWithoutCoords = DB::table('schools')->where(function ($q): void {
            $q->whereNull('latitude')->orWhereNull('longitude');
        })->count();
        $totalSchools = DB::table('schools')->count();

        $provinces = DB::table('wilayah')
            ->whereRaw('LENGTH(kode) = 2')
            ->orderBy('nama', 'asc')
            ->select('kode', 'nama')
            ->get();

        $sportCategories = DB::table('sport_categories')
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->select('id', 'name')
            ->get();

        return [
            'stats' => [
                'total_venues' => $totalVenues,
                'total_communities' => $totalCommunities,
                'verified_communities' => $verifiedCommunities,
                'unverified_communities' => $unverifiedCommunities,
                'schools_with_coords' => $schoolsWithCoords,
                'schools_without_coords' => $schoolsWithoutCoords,
                'total_schools' => $totalSchools,
            ],
            'provinces' => $provinces,
            'sport_categories' => $sportCategories,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{south: float, west: float, north: float, east: float}|null  $bbox
     * @param  list<string>  $layers
     * @return array{data: list<array<string, mixed>>, truncated: bool, count: int}
     */
    public function getMarkers(array $filters, ?array $bbox, array $layers): array
    {
        $limit = 2000;
        $items = collect();

        $includeSchools = in_array('school', $layers, true);
        $includeVenues = in_array('venue', $layers, true);
        $includeVerified = in_array('verified_comm', $layers, true);
        $includeUnverified = in_array('unverified_comm', $layers, true);

        if ($includeVenues && $items->count() <= $limit) {
            $q = DB::table('venues as v')
                ->leftJoin('wilayah as w', 'w.kode', '=', 'v.city_id')
                ->where('v.is_active', true)
                ->whereNotNull('v.latitude')
                ->whereNotNull('v.longitude');

            $this->applyRegionAndBbox($q, 'v', $filters, $bbox);

            $venues = $q->selectRaw("v.id, 'venue' as type, v.name, v.latitude as lat, v.longitude as lng, COALESCE(w.nama, v.city_id, '') as city")
                ->limit($limit + 1 - $items->count())
                ->get();

            $items = $items->concat($venues);
        }

        if (($includeVerified || $includeUnverified) && $items->count() <= $limit) {
            $q = DB::table('communities as c')
                ->leftJoin('wilayah as w', 'w.kode', '=', 'c.city_id')
                ->whereNull('c.deleted_at')
                ->whereNotNull('c.latitude')
                ->whereNotNull('c.longitude');

            if ($includeVerified && ! $includeUnverified) {
                $q->where('c.status', 'verified');
            } elseif (! $includeVerified && $includeUnverified) {
                $q->where('c.status', '!=', 'verified');
            }

            if (! empty($filters['sport_category_id'])) {
                $q->where('c.sport_category_id', $filters['sport_category_id']);
            }

            $this->applyRegionAndBbox($q, 'c', $filters, $bbox);

            $communities = $q->selectRaw("c.id, CASE WHEN c.status = 'verified' THEN 'verified_comm' ELSE 'unverified_comm' END as type, c.name, c.latitude as lat, c.longitude as lng, COALESCE(w.nama, c.city_id, '') as city")
                ->limit($limit + 1 - $items->count())
                ->get();

            $items = $items->concat($communities);
        }

        if ($includeSchools && $items->count() <= $limit) {
            $q = DB::table('schools as s')
                ->leftJoin('wilayah as w', 'w.kode', '=', 's.city_id')
                ->whereNotNull('s.latitude')
                ->whereNotNull('s.longitude');

            $this->applyRegionAndBbox($q, 's', $filters, $bbox);

            if (! empty($filters['school_level'])) {
                $q->where('s.school_level', $filters['school_level']);
            }

            $schools = $q->selectRaw("s.id, 'school' as type, s.name, s.latitude as lat, s.longitude as lng, COALESCE(w.nama, s.city_id, '') as city")
                ->limit($limit + 1 - $items->count())
                ->get();

            $items = $items->concat($schools);
        }

        $truncated = $items->count() > $limit;
        $sliced = $items->take($limit);

        $data = $sliced->map(function ($row): array {
            return [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'name' => (string) $row->name,
                'lat' => (float) $row->lat,
                'lng' => (float) $row->lng,
                'city' => (string) ($row->city ?? ''),
            ];
        })->values()->all();

        return [
            'data' => $data,
            'truncated' => $truncated,
            'count' => count($data),
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>}
     */
    public function search(string $keyword): array
    {
        $keyword = trim($keyword);
        if (mb_strlen($keyword) < 1) {
            return ['data' => []];
        }

        $escaped = addcslashes($keyword, '%_\\');
        $schools = DB::table('schools as s')
            ->leftJoin('wilayah as w', 'w.kode', '=', 's.city_id')
            ->whereNotNull('s.latitude')
            ->whereNotNull('s.longitude')
            ->where(function ($q) use ($escaped): void {
                $q->where('s.name', 'like', "%{$escaped}%")
                    ->orWhere('s.npsn', 'like', "%{$escaped}%");
            })
            ->selectRaw("s.id, 'school' as type, s.name, s.latitude as lat, s.longitude as lng, COALESCE(w.nama, s.city_id, '') as city")
            ->limit(10)
            ->get();

        $venues = DB::table('venues as v')
            ->leftJoin('wilayah as w', 'w.kode', '=', 'v.city_id')
            ->where('v.is_active', true)
            ->whereNotNull('v.latitude')
            ->whereNotNull('v.longitude')
            ->where('v.name', 'like', "%{$escaped}%")
            ->selectRaw("v.id, 'venue' as type, v.name, v.latitude as lat, v.longitude as lng, COALESCE(w.nama, v.city_id, '') as city")
            ->limit(5)
            ->get();

        $communities = DB::table('communities as c')
            ->leftJoin('wilayah as w', 'w.kode', '=', 'c.city_id')
            ->whereNull('c.deleted_at')
            ->whereNotNull('c.latitude')
            ->whereNotNull('c.longitude')
            ->where('c.name', 'like', "%{$escaped}%")
            ->selectRaw("c.id, CASE WHEN c.status = 'verified' THEN 'verified_comm' ELSE 'unverified_comm' END as type, c.name, c.latitude as lat, c.longitude as lng, COALESCE(w.nama, c.city_id, '') as city")
            ->limit(5)
            ->get();

        /** @var Collection<int, object> $combined */
        $combined = $schools->concat($venues)->concat($communities)->take(20);

        $data = $combined->map(function ($row): array {
            return [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'name' => (string) $row->name,
                'lat' => (float) $row->lat,
                'lng' => (float) $row->lng,
                'city' => (string) ($row->city ?? ''),
            ];
        })->values()->all();

        return ['data' => $data];
    }

    /**
     * @param  Builder  $query
     * @param  array<string, mixed>  $filters
     * @param  array{south: float, west: float, north: float, east: float}|null  $bbox
     */
    protected function applyRegionAndBbox($query, string $alias, array $filters, ?array $bbox): void
    {
        if ($bbox !== null) {
            $query->whereBetween("{$alias}.latitude", [$bbox['south'], $bbox['north']])
                ->whereBetween("{$alias}.longitude", [$bbox['west'], $bbox['east']]);
        }

        if (! empty($filters['province_id'])) {
            $query->where("{$alias}.province_id", $filters['province_id']);
        }

        if (! empty($filters['city_id'])) {
            $query->where("{$alias}.city_id", $filters['city_id']);
        }

        if (! empty($filters['region_code'])) {
            $regionCode = (string) $filters['region_code'];
            $len = strlen($regionCode);
            if ($len <= 2) {
                $query->where("{$alias}.province_id", $regionCode);
            } else {
                $query->where("{$alias}.city_id", $regionCode);
            }
        }
    }
}
