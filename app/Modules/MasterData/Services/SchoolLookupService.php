<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Services;

use App\Contracts\DTOs\SchoolSummary;
use App\Contracts\SchoolLookup;
use App\Modules\MasterData\Models\School;
use Illuminate\Support\Facades\DB;

class SchoolLookupService implements SchoolLookup
{
    public function findByNpsn(string $npsn): ?SchoolSummary
    {
        $trimmed = trim($npsn);
        if ($trimmed === '') {
            return null;
        }

        $school = School::where('npsn', $trimmed)->first();
        if (! $school) {
            return null;
        }

        $isRegistered = DB::table('communities')->where('npsn', $school->npsn)->exists();

        return new SchoolSummary(
            id: (int) $school->id,
            npsn: (string) $school->npsn,
            name: (string) $school->name,
            school_level: (string) $school->school_level,
            school_status: $school->school_status ? (string) $school->school_status : null,
            province_id: $school->province_id ? (string) $school->province_id : null,
            city_id: $school->city_id ? (string) $school->city_id : null,
            address: $school->address ? (string) $school->address : null,
            is_registered: $isRegistered,
            latitude: $school->latitude !== null ? (float) $school->latitude : null,
            longitude: $school->longitude !== null ? (float) $school->longitude : null,
        );
    }

    /**
     * @return list<SchoolSummary>
     */
    public function search(string $query, ?string $level = null, ?string $cityId = null, int $limit = 20): array
    {
        $q = trim($query);
        if (strlen($q) < 2) {
            return [];
        }

        $schoolsQuery = School::query()
            ->where(function ($sub) use ($q): void {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('npsn', 'like', "%{$q}%");
            });

        if ($level) {
            $levels = match ($level) {
                'school_sd', 'sd' => ['sd'],
                'school_smp', 'smp' => ['smp'],
                'school_sma', 'sma' => ['sma', 'smk'],
                'smk' => ['smk'],
                default => [$level],
            };
            $schoolsQuery->whereIn('school_level', $levels);
        }

        if ($cityId) {
            $schoolsQuery->where('city_id', $cityId);
        }

        $schools = $schoolsQuery->orderBy('name')->limit($limit)->get();

        if ($schools->isEmpty()) {
            return [];
        }

        $registeredNpsns = DB::table('communities')
            ->whereIn('npsn', $schools->pluck('npsn'))
            ->pluck('npsn')
            ->flip();

        return $schools->map(fn (School $s) => new SchoolSummary(
            id: (int) $s->id,
            npsn: (string) $s->npsn,
            name: (string) $s->name,
            school_level: (string) $s->school_level,
            school_status: $s->school_status ? (string) $s->school_status : null,
            province_id: $s->province_id ? (string) $s->province_id : null,
            city_id: $s->city_id ? (string) $s->city_id : null,
            address: $s->address ? (string) $s->address : null,
            is_registered: isset($registeredNpsns[$s->npsn]),
            latitude: $s->latitude !== null ? (float) $s->latitude : null,
            longitude: $s->longitude !== null ? (float) $s->longitude : null,
        ))->all();
    }

    public function deriveSubtypeToken(?string $schoolLevel): ?string
    {
        if (! $schoolLevel) {
            return null;
        }

        return match (strtolower($schoolLevel)) {
            'sd' => 'school_sd',
            'smp' => 'school_smp',
            'sma', 'smk' => 'school_sma',
            default => null,
        };
    }
}
