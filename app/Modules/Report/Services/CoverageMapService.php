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
        $includeCommunities = $includeVerified || $includeUnverified;
        $bothSchoolAndComm = $includeSchools && $includeCommunities;

        $hasActivitySportCol = $this->hasActivityColumn('sport_category_id');
        $hasActivityIsActiveCol = $this->hasActivityColumn('is_active');

        if ($includeVenues && $items->count() <= $limit) {
            $q = DB::table('venues as v')
                ->leftJoin('wilayah as w', 'w.kode', '=', 'v.city_id')
                ->where('v.is_active', true)
                ->whereNotNull('v.latitude')
                ->whereNotNull('v.longitude')
                ->whereRaw('NOT (v.latitude = 0 AND v.longitude = 0)');

            $this->applyRegionAndBbox($q, 'v', $filters, $bbox);

            $venues = $q->selectRaw("v.id, 'venue' as type, v.name, v.latitude as lat, v.longitude as lng, COALESCE(w.nama, v.city_id, '') as city, 'venue' as entity_subtype, NULL as community_id, NULL as npsn, 0 as activity_count")
                ->limit($limit + 1 - $items->count())
                ->get();

            $items = $items->concat($venues);
        }

        if ($includeCommunities && $items->count() <= $limit) {
            $q = DB::table('communities as c')
                ->leftJoin('wilayah as w', 'w.kode', '=', 'c.city_id')
                ->whereNull('c.deleted_at')
                ->whereNotNull('c.latitude')
                ->whereNotNull('c.longitude')
                ->whereRaw('NOT (c.latitude = 0 AND c.longitude = 0)');

            if ($includeVerified && ! $includeUnverified) {
                $q->where('c.status', 'verified');
            } elseif (! $includeVerified && $includeUnverified) {
                $q->where('c.status', '!=', 'verified');
            }

            // Deduplication: avoid duplicate school+community markers for same NPSN when both layers selected
            if ($bothSchoolAndComm) {
                $q->whereNot(function ($sub): void {
                    $sub->where('c.subtype', 'school')
                        ->whereNotNull('c.npsn')
                        ->where('c.npsn', '!=', '')
                        ->whereExists(function ($sQuery): void {
                            $sQuery->select(DB::raw(1))
                                ->from('schools as s')
                                ->whereColumn('s.npsn', 'c.npsn');
                        });
                });
            }

            if (! empty($filters['sport_category_id'])) {
                $cabor = (int) $filters['sport_category_id'];
                $q->where(function ($sub) use ($cabor, $hasActivitySportCol, $hasActivityIsActiveCol): void {
                    $sub->where('c.sport_category_id', $cabor)
                        ->orWhereExists(function ($actQuery) use ($cabor, $hasActivitySportCol, $hasActivityIsActiveCol): void {
                            $actQuery->select(DB::raw(1))
                                ->from('community_activities as a')
                                ->whereColumn('a.community_id', 'c.id')
                                ->where('a.status', '!=', 'cancelled');
                            if ($hasActivityIsActiveCol) {
                                $actQuery->where('a.is_active', true);
                            } else {
                                $actQuery->whereIn('a.status', ['scheduled', 'ongoing']);
                            }
                            if ($hasActivitySportCol) {
                                $actQuery->whereRaw('COALESCE(a.sport_category_id, c.sport_category_id) = ?', [$cabor]);
                            } else {
                                $actQuery->where('c.sport_category_id', $cabor);
                            }
                        })
                        ->orWhereExists(function ($epQuery) use ($cabor): void {
                            $epQuery->select(DB::raw(1))
                                ->from('event_participants as ep')
                                ->join('events as e', 'e.id', '=', 'ep.event_id')
                                ->whereColumn('ep.community_id', 'c.id')
                                ->where('ep.status', '!=', 'cancelled')
                                ->whereNull('e.deleted_at')
                                ->where('e.status', '!=', 'cancelled')
                                ->where('e.sport_category_id', $cabor);
                        });
                });
            }

            $this->applyRegionAndBbox($q, 'c', $filters, $bbox);

            $communities = $q->selectRaw("c.id, CASE WHEN c.status = 'verified' THEN 'verified_comm' ELSE 'unverified_comm' END as type, c.name, c.latitude as lat, c.longitude as lng, COALESCE(w.nama, c.city_id, '') as city, c.subtype as entity_subtype, c.id as community_id, c.npsn as npsn")
                ->limit($limit + 1 - $items->count())
                ->get();

            // Bulk activity counts for communities
            $commIds = $communities->pluck('id')->all();
            $commActivityCounts = [];
            if (! empty($commIds)) {
                $actQ = DB::table('community_activities')
                    ->whereIn('community_id', $commIds)
                    ->where('status', '!=', 'cancelled');
                if ($hasActivityIsActiveCol) {
                    $actQ->where('is_active', true);
                } else {
                    $actQ->whereIn('status', ['scheduled', 'ongoing']);
                }
                $commActivityCounts = $actQ->groupBy('community_id')
                    ->pluck(DB::raw('count(*)'), 'community_id')
                    ->all();
            }

            foreach ($communities as $c) {
                $c->activity_count = $commActivityCounts[$c->id] ?? 0;
            }

            $items = $items->concat($communities);
        }

        if ($includeSchools && $items->count() <= $limit) {
            $q = DB::table('schools as s')
                ->leftJoin('wilayah as w', 'w.kode', '=', 's.city_id')
                ->leftJoin(DB::raw('(SELECT npsn, MIN(id) as id, MAX(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL AND NOT (latitude = 0 AND longitude = 0) THEN latitude END) as manual_lat, MAX(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL AND NOT (latitude = 0 AND longitude = 0) THEN longitude END) as manual_lng FROM communities WHERE subtype = \'school\' AND deleted_at IS NULL AND npsn IS NOT NULL AND npsn != \'\' GROUP BY npsn) as comm_override'), 'comm_override.npsn', '=', 's.npsn')
                ->whereRaw('COALESCE(comm_override.manual_lat, s.latitude) IS NOT NULL')
                ->whereRaw('COALESCE(comm_override.manual_lng, s.longitude) IS NOT NULL')
                ->whereRaw('NOT (COALESCE(comm_override.manual_lat, s.latitude) = 0 AND COALESCE(comm_override.manual_lng, s.longitude) = 0)');

            if ($bbox !== null) {
                $q->whereBetween(DB::raw('COALESCE(comm_override.manual_lat, s.latitude)'), [$bbox['south'], $bbox['north']])
                    ->whereBetween(DB::raw('COALESCE(comm_override.manual_lng, s.longitude)'), [$bbox['west'], $bbox['east']]);
            }

            if (! empty($filters['province_id'])) {
                $q->where('s.province_id', $filters['province_id']);
            }

            if (! empty($filters['city_id'])) {
                $q->where('s.city_id', $filters['city_id']);
            }

            if (! empty($filters['region_code'])) {
                $regionCode = (string) $filters['region_code'];
                if (strlen($regionCode) <= 2) {
                    $q->where('s.province_id', $regionCode);
                } else {
                    $q->where('s.city_id', $regionCode);
                }
            }

            if (! empty($filters['school_level'])) {
                $level = (string) $filters['school_level'];
                $levels = match (strtolower($level)) {
                    'school_sd', 'sd' => ['sd'],
                    'school_smp', 'smp' => ['smp'],
                    'school_sma', 'sma' => ['sma', 'smk'],
                    'smk' => ['smk'],
                    default => [$level],
                };
                $q->whereIn('s.school_level', $levels);
            }

            if (! empty($filters['sport_category_id'])) {
                $cabor = (int) $filters['sport_category_id'];
                $q->whereExists(function ($sub) use ($cabor, $hasActivitySportCol, $hasActivityIsActiveCol): void {
                    $sub->select(DB::raw(1))
                        ->from('communities as c')
                        ->where('c.subtype', 'school')
                        ->whereNull('c.deleted_at')
                        ->whereColumn('c.npsn', 's.npsn')
                        ->where(function ($caborSub) use ($cabor, $hasActivitySportCol, $hasActivityIsActiveCol): void {
                            $caborSub->where('c.sport_category_id', $cabor)
                                ->orWhereExists(function ($actQuery) use ($cabor, $hasActivitySportCol, $hasActivityIsActiveCol): void {
                                    $actQuery->select(DB::raw(1))
                                        ->from('community_activities as a')
                                        ->whereColumn('a.community_id', 'c.id')
                                        ->where('a.status', '!=', 'cancelled');
                                    if ($hasActivityIsActiveCol) {
                                        $actQuery->where('a.is_active', true);
                                    } else {
                                        $actQuery->whereIn('a.status', ['scheduled', 'ongoing']);
                                    }
                                    if ($hasActivitySportCol) {
                                        $actQuery->whereRaw('COALESCE(a.sport_category_id, c.sport_category_id) = ?', [$cabor]);
                                    } else {
                                        $actQuery->where('c.sport_category_id', $cabor);
                                    }
                                })
                                ->orWhereExists(function ($epQuery) use ($cabor): void {
                                    $epQuery->select(DB::raw(1))
                                        ->from('event_participants as ep')
                                        ->join('events as e', 'e.id', '=', 'ep.event_id')
                                        ->whereColumn('ep.community_id', 'c.id')
                                        ->where('ep.status', '!=', 'cancelled')
                                        ->whereNull('e.deleted_at')
                                        ->where('e.status', '!=', 'cancelled')
                                        ->where('e.sport_category_id', $cabor);
                                });
                        });
                });
            }

            $schools = $q->selectRaw("s.id, 'school' as type, s.name, COALESCE(comm_override.manual_lat, s.latitude) as lat, COALESCE(comm_override.manual_lng, s.longitude) as lng, COALESCE(w.nama, s.city_id, '') as city, s.school_level, comm_override.id as community_id, s.npsn")
                ->limit($limit + 1 - $items->count())
                ->get();

            // Bulk activity counts for schools
            $schoolCommIds = array_filter($schools->pluck('community_id')->all());
            $schoolActivityCounts = [];
            if (! empty($schoolCommIds)) {
                $actQ = DB::table('community_activities')
                    ->whereIn('community_id', $schoolCommIds)
                    ->where('status', '!=', 'cancelled');
                if ($hasActivityIsActiveCol) {
                    $actQ->where('is_active', true);
                } else {
                    $actQ->whereIn('status', ['scheduled', 'ongoing']);
                }
                $schoolActivityCounts = $actQ->groupBy('community_id')
                    ->pluck(DB::raw('count(*)'), 'community_id')
                    ->all();
            }

            foreach ($schools as $s) {
                $s->entity_subtype = $this->deriveSchoolSubtype($s->school_level);
                $s->activity_count = $s->community_id ? ($schoolActivityCounts[$s->community_id] ?? 0) : 0;
            }

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
                'entity_subtype' => (string) ($row->entity_subtype ?? ''),
                'community_id' => isset($row->community_id) && $row->community_id !== null ? (int) $row->community_id : null,
                'npsn' => isset($row->npsn) && $row->npsn !== null && $row->npsn !== '' ? (string) $row->npsn : null,
                'activity_count' => (int) ($row->activity_count ?? 0),
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

    /**
     * @return array<string, mixed>|null
     */
    public function getEntityDetails(string $type, int $id): ?array
    {
        return match ($type) {
            'school' => $this->getSchoolDetails($id),
            'community' => $this->getCommunityDetails($id),
            'venue' => $this->getVenueDetails($id),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getSchoolDetails(int $id): ?array
    {
        $school = DB::table('schools')->where('id', $id)->first();
        if (! $school) {
            return null;
        }

        $communities = collect();
        if (! empty($school->npsn)) {
            $communities = DB::table('communities')
                ->where('npsn', $school->npsn)
                ->where('subtype', 'school')
                ->whereNull('deleted_at')
                ->get();
        }

        $overrideComm = $communities->first(function ($c): bool {
            return $this->isValidCoordinate(
                $c->latitude !== null ? (float) $c->latitude : null,
                $c->longitude !== null ? (float) $c->longitude : null
            );
        });

        $lat = null;
        $lng = null;
        $coordinateSource = null;

        if ($overrideComm) {
            $lat = (float) $overrideComm->latitude;
            $lng = (float) $overrideComm->longitude;
            $coordinateSource = 'profile';
        } elseif ($this->isValidCoordinate($school->latitude !== null ? (float) $school->latitude : null, $school->longitude !== null ? (float) $school->longitude : null)) {
            $lat = (float) $school->latitude;
            $lng = (float) $school->longitude;
            $coordinateSource = 'master';
        }

        $status = null;
        if ($communities->isNotEmpty()) {
            $hasVerified = $communities->contains(fn ($c) => $c->status === 'verified');
            $status = $hasVerified ? 'verified' : (string) $communities->first()->status;
        } else {
            $status = $school->school_status ? (string) $school->school_status : 'unregistered';
        }

        $communityIds = $communities->pluck('id')->map(fn ($cid) => (int) $cid)->all();

        $activities = $this->getActivitiesForCommunities($communityIds);
        $events = $this->getEventsForCommunities($communityIds);
        $sports = $this->resolveSportsForCommunities($communities, $activities, $events);

        $primaryComm = $communities->first();
        $latestEvent = $events[0] ?? null;

        return [
            'entity' => [
                'id' => (int) $school->id,
                'type' => 'school',
                'name' => (string) $school->name,
                'subtype' => $this->deriveSchoolSubtype($school->school_level),
                'npsn' => $school->npsn ? (string) $school->npsn : null,
                'latitude' => $lat,
                'longitude' => $lng,
                'address' => $school->address ? (string) $school->address : ($overrideComm?->address ? (string) $overrideComm->address : null),
                'status' => $status,
                'coordinate_source' => $coordinateSource,
            ],
            'sports' => $sports,
            'activities' => $activities,
            'events' => $events,
            'links' => [
                'school' => [
                    'id' => (int) $school->id,
                    'name' => (string) $school->name,
                    'npsn' => (string) $school->npsn,
                ],
                'community' => $primaryComm ? [
                    'id' => (int) $primaryComm->id,
                    'name' => (string) $primaryComm->name,
                ] : null,
                'event' => $latestEvent ? [
                    'id' => (int) $latestEvent['id'],
                    'title' => (string) $latestEvent['title'],
                ] : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getCommunityDetails(int $id): ?array
    {
        $community = DB::table('communities')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();
        if (! $community) {
            return null;
        }

        $masterSchool = null;
        if (! empty($community->npsn)) {
            $masterSchool = DB::table('schools')->where('npsn', $community->npsn)->first();
        }

        $lat = null;
        $lng = null;
        $coordinateSource = null;

        if ($this->isValidCoordinate($community->latitude !== null ? (float) $community->latitude : null, $community->longitude !== null ? (float) $community->longitude : null)) {
            $lat = (float) $community->latitude;
            $lng = (float) $community->longitude;
            $coordinateSource = 'profile';
        } elseif ($masterSchool && $this->isValidCoordinate($masterSchool->latitude !== null ? (float) $masterSchool->latitude : null, $masterSchool->longitude !== null ? (float) $masterSchool->longitude : null)) {
            $lat = (float) $masterSchool->latitude;
            $lng = (float) $masterSchool->longitude;
            $coordinateSource = 'master';
        }

        $activities = $this->getActivitiesForCommunities([(int) $community->id]);
        $events = $this->getEventsForCommunities([(int) $community->id]);
        $sports = $this->resolveSportsForCommunities(collect([$community]), $activities, $events);

        $latestEvent = $events[0] ?? null;

        return [
            'entity' => [
                'id' => (int) $community->id,
                'type' => 'community',
                'name' => (string) $community->name,
                'subtype' => (string) $community->subtype,
                'npsn' => $community->npsn ? (string) $community->npsn : null,
                'latitude' => $lat,
                'longitude' => $lng,
                'address' => $community->address ? (string) $community->address : ($masterSchool?->address ? (string) $masterSchool->address : null),
                'status' => (string) $community->status,
                'coordinate_source' => $coordinateSource,
            ],
            'sports' => $sports,
            'activities' => $activities,
            'events' => $events,
            'links' => [
                'school' => $masterSchool ? [
                    'id' => (int) $masterSchool->id,
                    'name' => (string) $masterSchool->name,
                    'npsn' => (string) $masterSchool->npsn,
                ] : null,
                'community' => [
                    'id' => (int) $community->id,
                    'name' => (string) $community->name,
                ],
                'event' => $latestEvent ? [
                    'id' => (int) $latestEvent['id'],
                    'title' => (string) $latestEvent['title'],
                ] : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getVenueDetails(int $id): ?array
    {
        $venue = DB::table('venues')->where('id', $id)->first();
        if (! $venue) {
            return null;
        }

        $lat = null;
        $lng = null;
        $coordinateSource = null;

        if ($this->isValidCoordinate($venue->latitude !== null ? (float) $venue->latitude : null, $venue->longitude !== null ? (float) $venue->longitude : null)) {
            $lat = (float) $venue->latitude;
            $lng = (float) $venue->longitude;
            $coordinateSource = 'master';
        }

        $activities = $this->getActivitiesForVenue((int) $venue->id);
        $events = $this->getEventsForVenue((int) $venue->id);
        $sports = $this->resolveSportsForVenue($activities, $events);

        $latestEvent = $events[0] ?? null;

        return [
            'entity' => [
                'id' => (int) $venue->id,
                'type' => 'venue',
                'name' => (string) $venue->name,
                'subtype' => 'venue',
                'npsn' => null,
                'latitude' => $lat,
                'longitude' => $lng,
                'address' => $venue->address ? (string) $venue->address : null,
                'status' => $venue->is_active ? 'active' : 'inactive',
                'coordinate_source' => $coordinateSource,
            ],
            'sports' => $sports,
            'activities' => $activities,
            'events' => $events,
            'links' => [
                'school' => null,
                'community' => null,
                'event' => $latestEvent ? [
                    'id' => (int) $latestEvent['id'],
                    'title' => (string) $latestEvent['title'],
                ] : null,
            ],
        ];
    }

    /**
     * @param  list<int>  $communityIds
     * @return list<array<string, mixed>>
     */
    protected function getActivitiesForCommunities(array $communityIds): array
    {
        if (empty($communityIds)) {
            return [];
        }

        $hasSportCol = $this->hasActivityColumn('sport_category_id');
        $hasIsActiveCol = $this->hasActivityColumn('is_active');
        $hasScheduleCol = $this->hasActivityColumn('schedule');
        $hasLocationCol = $this->hasActivityColumn('location');

        $q = DB::table('community_activities as a')
            ->leftJoin('communities as c', 'c.id', '=', 'a.community_id')
            ->leftJoin('venues as v', 'v.id', '=', 'a.venue_id')
            ->whereIn('a.community_id', $communityIds)
            ->where('a.status', '!=', 'cancelled');

        if ($hasIsActiveCol) {
            $q->where('a.is_active', true);
        } else {
            $q->whereIn('a.status', ['scheduled', 'ongoing']);
        }

        $selects = [
            'a.id',
            'a.title as name',
            'a.description',
            'a.scheduled_at',
            'c.sport_category_id as comm_sport_id',
            'v.name as venue_name',
        ];

        if ($hasSportCol) {
            $selects[] = 'a.sport_category_id as act_sport_id';
        }
        if ($hasScheduleCol) {
            $selects[] = 'a.schedule';
        }
        if ($hasLocationCol) {
            $selects[] = 'a.location';
        }

        $rows = $q->select($selects)->orderBy('a.id', 'desc')->get();

        $sportIds = [];
        foreach ($rows as $r) {
            $sportId = ($hasSportCol && $r->act_sport_id) ? (int) $r->act_sport_id : ($r->comm_sport_id ? (int) $r->comm_sport_id : null);
            if ($sportId) {
                $sportIds[] = $sportId;
            }
        }
        $sportNames = [];
        if (! empty($sportIds)) {
            $sportNames = DB::table('sport_categories')
                ->whereIn('id', array_unique($sportIds))
                ->pluck('name', 'id')
                ->all();
        }

        return $rows->map(function ($r) use ($hasSportCol, $hasScheduleCol, $hasLocationCol, $sportNames): array {
            $sportId = ($hasSportCol && $r->act_sport_id) ? (int) $r->act_sport_id : ($r->comm_sport_id ? (int) $r->comm_sport_id : null);

            $schedule = null;
            if ($hasScheduleCol && ! empty($r->schedule)) {
                $schedule = (string) $r->schedule;
            } elseif (! empty($r->scheduled_at)) {
                $schedule = (string) $r->scheduled_at;
            }

            $location = null;
            if ($hasLocationCol && ! empty($r->location)) {
                $location = (string) $r->location;
            } elseif (! empty($r->venue_name)) {
                $location = (string) $r->venue_name;
            }

            return [
                'id' => (int) $r->id,
                'name' => (string) $r->name,
                'description' => $r->description ? (string) $r->description : null,
                'schedule' => $schedule,
                'location' => $location,
                'sport_category_id' => $sportId,
                'sport_name' => $sportId && isset($sportNames[$sportId]) ? (string) $sportNames[$sportId] : null,
            ];
        })->values()->all();
    }

    /**
     * @param  list<int>  $communityIds
     * @return list<array<string, mixed>>
     */
    protected function getEventsForCommunities(array $communityIds): array
    {
        if (empty($communityIds)) {
            return [];
        }

        $rows = DB::table('event_participants as ep')
            ->join('events as e', 'e.id', '=', 'ep.event_id')
            ->leftJoin('event_categories as ec', 'ec.id', '=', 'ep.event_category_id')
            ->leftJoin('sport_categories as sc', 'sc.id', '=', 'e.sport_category_id')
            ->whereIn('ep.community_id', $communityIds)
            ->where('ep.status', '!=', 'cancelled')
            ->whereNull('e.deleted_at')
            ->where('e.status', '!=', 'cancelled')
            ->orderBy('e.starts_at', 'desc')
            ->limit(20)
            ->select([
                'e.id',
                'e.title',
                'e.starts_at',
                'e.status',
                'sc.name as sport_name',
                'ec.name as category_name',
                'ep.status as participant_status',
            ])
            ->get();

        return $rows->map(function ($r): array {
            return [
                'id' => (int) $r->id,
                'title' => (string) $r->title,
                'starts_at' => $r->starts_at ? (string) $r->starts_at : null,
                'status' => (string) $r->status,
                'sport_name' => $r->sport_name ? (string) $r->sport_name : null,
                'category_name' => $r->category_name ? (string) $r->category_name : null,
                'participant_status' => (string) $r->participant_status,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, object>  $communities
     * @param  list<array<string, mixed>>  $activities
     * @param  list<array<string, mixed>>  $events
     * @return list<array{id: int, name: string}>
     */
    protected function resolveSportsForCommunities(Collection $communities, array $activities, array $events): array
    {
        $sportIds = [];
        foreach ($communities as $c) {
            if (! empty($c->sport_category_id)) {
                $sportIds[] = (int) $c->sport_category_id;
            }
        }
        foreach ($activities as $a) {
            if (! empty($a['sport_category_id'])) {
                $sportIds[] = (int) $a['sport_category_id'];
            }
        }

        if (! empty($events)) {
            $eventIds = array_column($events, 'id');
            $eventSportIds = DB::table('events')
                ->whereIn('id', $eventIds)
                ->whereNotNull('sport_category_id')
                ->pluck('sport_category_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $sportIds = array_merge($sportIds, $eventSportIds);
        }

        $uniqueIds = array_values(array_unique(array_filter($sportIds)));
        if (empty($uniqueIds)) {
            return [];
        }

        return DB::table('sport_categories')
            ->whereIn('id', $uniqueIds)
            ->orderBy('name', 'asc')
            ->get(['id', 'name'])
            ->map(fn ($s) => ['id' => (int) $s->id, 'name' => (string) $s->name])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function getActivitiesForVenue(int $venueId): array
    {
        $hasSportCol = $this->hasActivityColumn('sport_category_id');
        $hasIsActiveCol = $this->hasActivityColumn('is_active');
        $hasScheduleCol = $this->hasActivityColumn('schedule');
        $hasLocationCol = $this->hasActivityColumn('location');

        $q = DB::table('community_activities as a')
            ->leftJoin('communities as c', 'c.id', '=', 'a.community_id')
            ->leftJoin('venues as v', 'v.id', '=', 'a.venue_id')
            ->where('a.venue_id', $venueId)
            ->where('a.status', '!=', 'cancelled');

        if ($hasIsActiveCol) {
            $q->where('a.is_active', true);
        } else {
            $q->whereIn('a.status', ['scheduled', 'ongoing']);
        }

        $selects = [
            'a.id',
            'a.title as name',
            'a.description',
            'a.scheduled_at',
            'c.sport_category_id as comm_sport_id',
            'v.name as venue_name',
        ];

        if ($hasSportCol) {
            $selects[] = 'a.sport_category_id as act_sport_id';
        }
        if ($hasScheduleCol) {
            $selects[] = 'a.schedule';
        }
        if ($hasLocationCol) {
            $selects[] = 'a.location';
        }

        $rows = $q->select($selects)->orderBy('a.id', 'desc')->get();

        $sportIds = [];
        foreach ($rows as $r) {
            $sportId = ($hasSportCol && $r->act_sport_id) ? (int) $r->act_sport_id : ($r->comm_sport_id ? (int) $r->comm_sport_id : null);
            if ($sportId) {
                $sportIds[] = $sportId;
            }
        }
        $sportNames = [];
        if (! empty($sportIds)) {
            $sportNames = DB::table('sport_categories')
                ->whereIn('id', array_unique($sportIds))
                ->pluck('name', 'id')
                ->all();
        }

        return $rows->map(function ($r) use ($hasSportCol, $hasScheduleCol, $hasLocationCol, $sportNames): array {
            $sportId = ($hasSportCol && $r->act_sport_id) ? (int) $r->act_sport_id : ($r->comm_sport_id ? (int) $r->comm_sport_id : null);

            $schedule = null;
            if ($hasScheduleCol && ! empty($r->schedule)) {
                $schedule = (string) $r->schedule;
            } elseif (! empty($r->scheduled_at)) {
                $schedule = (string) $r->scheduled_at;
            }

            $location = null;
            if ($hasLocationCol && ! empty($r->location)) {
                $location = (string) $r->location;
            } elseif (! empty($r->venue_name)) {
                $location = (string) $r->venue_name;
            }

            return [
                'id' => (int) $r->id,
                'name' => (string) $r->name,
                'description' => $r->description ? (string) $r->description : null,
                'schedule' => $schedule,
                'location' => $location,
                'sport_category_id' => $sportId,
                'sport_name' => $sportId && isset($sportNames[$sportId]) ? (string) $sportNames[$sportId] : null,
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function getEventsForVenue(int $venueId): array
    {
        $rows = DB::table('events as e')
            ->leftJoin('sport_categories as sc', 'sc.id', '=', 'e.sport_category_id')
            ->where('e.venue_id', $venueId)
            ->whereNull('e.deleted_at')
            ->where('e.status', '!=', 'cancelled')
            ->orderBy('e.starts_at', 'desc')
            ->limit(20)
            ->select([
                'e.id',
                'e.title',
                'e.starts_at',
                'e.status',
                'sc.name as sport_name',
            ])
            ->get();

        return $rows->map(function ($r): array {
            return [
                'id' => (int) $r->id,
                'title' => (string) $r->title,
                'starts_at' => $r->starts_at ? (string) $r->starts_at : null,
                'status' => (string) $r->status,
                'sport_name' => $r->sport_name ? (string) $r->sport_name : null,
                'category_name' => null,
                'participant_status' => null,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $activities
     * @param  list<array<string, mixed>>  $events
     * @return list<array{id: int, name: string}>
     */
    protected function resolveSportsForVenue(array $activities, array $events): array
    {
        $sportIds = [];
        foreach ($activities as $a) {
            if (! empty($a['sport_category_id'])) {
                $sportIds[] = (int) $a['sport_category_id'];
            }
        }
        if (! empty($events)) {
            $eventIds = array_column($events, 'id');
            $eventSportIds = DB::table('events')
                ->whereIn('id', $eventIds)
                ->whereNotNull('sport_category_id')
                ->pluck('sport_category_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $sportIds = array_merge($sportIds, $eventSportIds);
        }

        $uniqueIds = array_values(array_unique(array_filter($sportIds)));
        if (empty($uniqueIds)) {
            return [];
        }

        return DB::table('sport_categories')
            ->whereIn('id', $uniqueIds)
            ->orderBy('name', 'asc')
            ->get(['id', 'name'])
            ->map(fn ($s) => ['id' => (int) $s->id, 'name' => (string) $s->name])
            ->values()
            ->all();
    }

    protected function isValidCoordinate(?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null) {
            return false;
        }

        if (abs($lat) < 0.000001 && abs($lng) < 0.000001) {
            return false;
        }

        return $lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0;
    }

    protected function deriveSchoolSubtype(?string $level): string
    {
        return match (strtolower((string) $level)) {
            'sd' => 'school_sd',
            'smp' => 'school_smp',
            'sma', 'smk' => 'school_sma',
            default => 'school',
        };
    }

    protected function hasActivityColumn(string $column): bool
    {
        static $cols = null;
        if ($cols === null) {
            $cols = array_flip(DB::getSchemaBuilder()->getColumnListing('community_activities'));
        }

        return isset($cols[$column]);
    }
}
