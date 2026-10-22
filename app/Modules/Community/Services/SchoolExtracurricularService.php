<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\SchoolExtracurriculars;
use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\SchoolExtracurricular;
use App\Modules\Community\Models\SchoolExtracurricularMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SchoolExtracurricularService implements SchoolExtracurriculars
{
    public function addMember(Community $community, int $athleteId, int $unitId, User $actor): void
    {
        DB::transaction(function () use ($community, $athleteId, $unitId, $actor): void {
            $school = Community::whereKey($community->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
            abort_unless($school->subtype === 'school' && $actor->fresh()?->is_active, 403);
            Gate::forUser($actor)->authorize('update', $school);
            $unit = SchoolExtracurricular::whereKey($unitId)->where('community_id', $school->id)->firstOrFail();
            $membership = CommunityAthlete::whereKey($athleteId)->where('community_id', $school->id)->lockForUpdate()->firstOrFail();
            if ($membership->status !== 'active' || $membership->left_at !== null || ! $membership->user()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['extracurricular_id' => 'Murid harus memiliki akun dan keanggotaan sekolah aktif.']);
            }
            $existing = SchoolExtracurricularMember::where('school_extracurricular_id', $unit->id)->where('community_athlete_id', $membership->id)->first();
            if ($existing?->left_at !== null) {
                throw ValidationException::withMessages(['extracurricular_id' => 'Keanggotaan ekskul telah berakhir; persetujuan bergabung kembali diperlukan.']);
            }
            if ($existing) {
                return;
            }
            $member = SchoolExtracurricularMember::create(['school_extracurricular_id' => $unit->id, 'community_athlete_id' => $membership->id, 'joined_at' => now()]);
            ActivityLog::create([
                'user_id' => $actor->id,
                'event' => 'created',
                'auditable_type' => $member->getMorphClass(),
                'auditable_id' => $member->id,
                'new_values' => ['school_extracurricular_id' => $unit->id, 'community_athlete_id' => $membership->id, 'source' => 'school_manager'],
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ]);
        });
    }

    public function unitsForDirectory(Community $community): Collection
    {
        return $community->subtype === 'school'
            ? DB::table('school_extracurriculars as units')
                ->join('sport_categories as sports', 'sports.id', '=', 'units.sport_category_id')
                ->where('units.community_id', $community->id)
                ->select('units.id', 'sports.name as sport_name')->orderBy('sports.name')->get()
            : collect();
    }

    public function membershipsForDirectory(Community $community, Collection $athletes): Collection
    {
        return $community->subtype === 'school'
            ? DB::table('school_extracurricular_members as members')
                ->join('school_extracurriculars as units', 'units.id', '=', 'members.school_extracurricular_id')
                ->join('sport_categories as sports', 'sports.id', '=', 'units.sport_category_id')
                ->where('units.community_id', $community->id)
                ->whereIn('members.community_athlete_id', $athletes->where('status', 'active')->whereNull('left_at')->pluck('id'))
                ->whereNull('members.left_at')
                ->select('members.community_athlete_id', 'units.id as unit_id', 'sports.name as sport_name')
                ->orderBy('sports.name')->get()->groupBy('community_athlete_id')
            : collect();
    }

    public function appointmentsForDirectory(Community $community, Collection $coaches): Collection
    {
        if ($community->subtype !== 'school') {
            return collect();
        }
        $appointments = DB::table('school_extracurricular_appointments as appointments')
            ->join('school_extracurriculars as units', 'units.id', '=', 'appointments.school_extracurricular_id')
            ->join('sport_categories as sports', 'sports.id', '=', 'units.sport_category_id')
            ->join('sports_personnel_roles as roles', 'roles.id', '=', 'appointments.sports_personnel_role_id')
            ->where('units.community_id', $community->id)
            ->whereIn('appointments.user_id', $coaches->pluck('user_id'))
            ->where('appointments.status', 'active')->whereNull('appointments.ended_at')
            ->where('roles.is_active', true)
            ->select('appointments.user_id', 'appointments.sports_personnel_role_id', 'roles.code as role_code', 'units.id as unit_id', 'sports.name as sport_name')
            ->orderBy('sports.name')->get()->groupBy('user_id');

        return $coaches->filter(fn ($coach) => $coach->status === 'active' && $coach->ended_at === null && $coach->user?->is_active)
            ->mapWithKeys(fn ($coach) => [$coach->id => $appointments->get($coach->user_id, collect())
                ->filter(fn ($appointment) => $coach->sports_personnel_role_id === null
                    ? $appointment->role_code === 'coach'
                    : (int) $appointment->sports_personnel_role_id === (int) $coach->sports_personnel_role_id)
                ->unique('unit_id')->values()]);
    }

    public function sync(int $communityId, int $sportCategoryId, array $userIds): ?int
    {
        return DB::transaction(function () use ($communityId, $sportCategoryId, $userIds): ?int {
            $school = Community::query()->whereKey($communityId)->lockForUpdate()->firstOrFail();
            if ($school->subtype !== 'school') {
                return null;
            }
            abort_unless($school->is_active, 403);
            $ids = array_values(array_unique(array_map('intval', $userIds)));
            $memberships = CommunityAthlete::query()->where('community_id', $school->id)
                ->whereIn('user_id', $ids)->whereIn('status', ['active', 'pending'])->whereNull('left_at')
                ->whereHas('user', fn ($q) => $q->where('is_active', true))->lockForUpdate()->get();
            if ($memberships->pluck('user_id')->unique()->count() !== count($ids)) {
                throw ValidationException::withMessages(['members' => 'Atlet harus menjadi anggota aktif sekolah dengan persetujuan yang sah.']);
            }
            $unit = SchoolExtracurricular::firstOrCreate(['community_id' => $school->id, 'sport_category_id' => $sportCategoryId]);
            foreach ($memberships as $membership) {
                if ($membership->status !== 'active') {
                    continue;
                }
                $existing = SchoolExtracurricularMember::query()->where('school_extracurricular_id', $unit->id)
                    ->where('community_athlete_id', $membership->id)->first();
                if ($existing?->left_at !== null) {
                    throw ValidationException::withMessages(['members' => 'Keanggotaan ekskul telah berakhir; persetujuan bergabung kembali diperlukan.']);
                }
                SchoolExtracurricularMember::firstOrCreate([
                    'school_extracurricular_id' => $unit->id,
                    'community_athlete_id' => $membership->id,
                ], ['joined_at' => now()]);
            }

            return (int) $unit->id;
        });
    }
}
