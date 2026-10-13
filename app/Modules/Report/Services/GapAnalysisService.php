<?php

declare(strict_types=1);

namespace App\Modules\Report\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Pengecualian eksplisit: Report membaca lewat DB::table() read-only, TANPA model Eloquent modul lain.
class GapAnalysisService
{
    public function getClassification(int $total, float $percentage): string
    {
        if ($total === 0) {
            return 'no_data';
        }
        if ($percentage <= 0.0) {
            return 'unreached';
        }
        if ($percentage < 5.0) {
            return 'very_low';
        }
        if ($percentage < 15.0) {
            return 'low';
        }
        if ($percentage < 30.0) {
            return 'medium';
        }

        return 'high';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getGapAnalysis(array $filters, bool $refresh = false): array
    {
        $cacheFilters = array_intersect_key($filters, array_flip(['level', 'parent_code', 'province_id', 'city_id', 'subdistrict_id', 'school_level']));
        ksort($cacheFilters);
        $cacheKey = 'report:gap:'.md5((string) json_encode($cacheFilters));
        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 900, function () use ($filters): array {
            $drillLevel = $this->determineDrillLevel($filters);
            $joinCol = match ($drillLevel) {
                'subdistrict' => 's.subdistrict_id',
                'city' => 's.city_id',
                default => 's.province_id',
            };

            $parentCode = $filters['parent_code'] ?? $filters['province_id'] ?? $filters['city_id'] ?? null;
            if ($parentCode !== null) {
                $parentCode = (string) $parentCode;
            }

            $query = DB::table('schools as s')
                ->leftJoin('wilayah as w', 'w.kode', '=', $joinCol)
                ->leftJoin('communities as cm', function ($join): void {
                    $join->on('cm.npsn', '=', 's.npsn')
                        ->where('cm.status', '=', 'verified')
                        ->whereNull('cm.deleted_at');
                });

            if (! empty($filters['school_level'])) {
                $query->where('s.school_level', $filters['school_level']);
            }

            if ($parentCode !== null && $parentCode !== '') {
                $query->where('w.kode', 'like', $parentCode.'%');
            }

            $rows = $query->selectRaw("COALESCE(w.kode, 'UNKNOWN') as region_code, COALESCE(w.nama, 'Lainnya / Tidak Terdata') as region_name, COUNT(DISTINCT s.id) as total, COUNT(DISTINCT cm.id) as reached")
                ->groupByRaw("COALESCE(w.kode, 'UNKNOWN'), COALESCE(w.nama, 'Lainnya / Tidak Terdata')")
                ->orderByRaw('(COUNT(DISTINCT s.id) - COUNT(DISTINCT cm.id)) DESC, COUNT(DISTINCT s.id) DESC')
                ->get();

            $totalSchools = 0;
            $reachedSchools = 0;

            $data = $rows->map(function ($row) use (&$totalSchools, &$reachedSchools): array {
                $total = (int) $row->total;
                $reached = (int) $row->reached;
                $gap = max(0, $total - $reached);
                $percentage = $total > 0 ? round(($reached / $total) * 100, 2) : 0.0;
                $classification = $this->getClassification($total, $percentage);

                $totalSchools += $total;
                $reachedSchools += $reached;

                return [
                    'region_code' => (string) $row->region_code,
                    'region_name' => (string) $row->region_name,
                    'total' => $total,
                    'reached' => $reached,
                    'gap' => $gap,
                    'percentage' => $percentage,
                    'classification' => $classification,
                ];
            })->all();

            $overallGap = max(0, $totalSchools - $reachedSchools);
            $overallPercentage = $totalSchools > 0 ? round(($reachedSchools / $totalSchools) * 100, 2) : 0.0;
            $overallClassification = $this->getClassification($totalSchools, $overallPercentage);

            return [
                'drill_level' => $drillLevel,
                'parent_code' => $parentCode,
                'filters' => $filters,
                'summary' => [
                    'total_schools' => $totalSchools,
                    'reached_schools' => $reachedSchools,
                    'gap_schools' => $overallGap,
                    'overall_percentage' => $overallPercentage,
                    'overall_classification' => $overallClassification,
                ],
                'regions' => $data,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getUnreachedSchools(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = DB::table('schools as s')
            ->leftJoin('communities as cm', function ($join): void {
                $join->on('cm.npsn', '=', 's.npsn')
                    ->where('cm.status', '=', 'verified')
                    ->whereNull('cm.deleted_at');
            })
            ->leftJoin('wilayah as w', 'w.kode', '=', 's.subdistrict_id')
            ->leftJoin('wilayah as wc', 'wc.kode', '=', 's.city_id')
            ->whereNull('cm.id');

        if (! empty($filters['school_level'])) {
            $query->where('s.school_level', $filters['school_level']);
        }

        if (! empty($filters['city_id'])) {
            $query->where('s.city_id', $filters['city_id']);
        }

        if (! empty($filters['subdistrict_id'])) {
            $query->where('s.subdistrict_id', $filters['subdistrict_id']);
        }

        if (! empty($filters['province_id'])) {
            $query->where('s.province_id', $filters['province_id']);
        }

        if (! empty($filters['parent_code'])) {
            $parentCode = (string) $filters['parent_code'];
            $query->where(function ($q) use ($parentCode): void {
                $q->where('s.province_id', $parentCode)
                    ->orWhere('s.city_id', $parentCode)
                    ->orWhere('s.city_id', 'like', $parentCode.'%')
                    ->orWhere('s.subdistrict_id', 'like', $parentCode.'%');
            });
        }

        return $query->select(
            's.id',
            's.npsn',
            's.name',
            's.school_level',
            's.school_status',
            's.address',
            's.city_id',
            's.subdistrict_id',
            'w.nama as subdistrict_name',
            'wc.nama as city_name'
        )->orderBy('s.name', 'asc')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function determineDrillLevel(array $filters): string
    {
        if (! empty($filters['level']) && in_array($filters['level'], ['province', 'city', 'subdistrict'], true)) {
            return (string) $filters['level'];
        }

        if (! empty($filters['city_id']) || (! empty($filters['parent_code']) && strlen((string) $filters['parent_code']) >= 4)) {
            return 'subdistrict';
        }

        if (! empty($filters['province_id']) || (! empty($filters['parent_code']) && strlen((string) $filters['parent_code']) <= 3)) {
            return 'city';
        }

        return 'province';
    }
}
