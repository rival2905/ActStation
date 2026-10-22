<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Modules\Community\Http\Requests\Panel\CoachDecisionRequest;
use App\Modules\Community\Http\Requests\Panel\PanelCoachIndexRequest;
use App\Modules\Community\Http\Requests\Panel\PanelCoachRequest;
use App\Modules\Community\Models\CommunityCoach;
use App\Modules\Community\Models\SportsPersonnelRole;
use App\Modules\Community\Services\SchoolExtracurricularService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CoachController extends PanelBaseController
{
    public function index(PanelCoachIndexRequest $request, SchoolExtracurricularService $extracurriculars): View
    {
        $community = $this->currentCommunity($request);
        $this->authorize('view', $community);
        $selectedExtracurricularId = $request->validated('extracurricular_id') !== null ? (int) $request->validated('extracurricular_id') : null;
        $schoolExtracurricularUnits = $extracurriculars->unitsForDirectory($community);
        abort_if($selectedExtracurricularId !== null && ! $schoolExtracurricularUnits->contains('id', $selectedExtracurricularId), 404);

        $coaches = $community->coaches()->with(['user', 'personnelRole'])->latest()->get();
        $schoolExtracurricularsByCoach = $extracurriculars->appointmentsForDirectory($community, $coaches);
        $allCoachesCount = $coaches->count();
        foreach ($schoolExtracurricularUnits as $unit) {
            $unit->member_count = $schoolExtracurricularsByCoach->filter(fn ($assignments) => $assignments->contains('unit_id', $unit->id))->count();
        }
        if ($selectedExtracurricularId !== null) {
            $coaches = $coaches->filter(fn ($coach) => $schoolExtracurricularsByCoach->get($coach->id, collect())->contains('unit_id', $selectedExtracurricularId))->values();
        }
        $personnelRoles = SportsPersonnelRole::where('is_active', true)->orderBy('sort_order')->get();
        $developmentLevels = DB::table('development_levels')->get();

        return view('panel.coaches.index', compact('community', 'coaches', 'developmentLevels', 'personnelRoles', 'schoolExtracurricularUnits', 'schoolExtracurricularsByCoach', 'allCoachesCount', 'selectedExtracurricularId'));
    }

    public function store(PanelCoachRequest $request): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        $data = $request->validated();
        $data['sports_personnel_role_id'] ??= SportsPersonnelRole::where('code', 'coach')->where('is_active', true)->value('id');
        abort_unless($data['sports_personnel_role_id'], 422, 'Pilih peran aktif.');
        $duplicates = $community->coaches()->where('user_id', $data['user_id'])->where('status', 'active')->whereNull('ended_at')
            ->where(function ($q) use ($data) {
                $q->where('sports_personnel_role_id', $data['sports_personnel_role_id']);
                if (SportsPersonnelRole::find($data['sports_personnel_role_id'])?->code === 'coach') {
                    $q->orWhereNull('sports_personnel_role_id');
                }
            })->exists();
        if ($duplicates) {
            throw ValidationException::withMessages(['user_id' => 'Akun sudah aktif pada peran tersebut.']);
        }
        $data['started_at'] = $data['started_at'] ?? now()->toDateString();
        $data['status'] = $data['status'] ?? 'active';

        $community->coaches()->create($data);

        return redirect()
            ->route('panel.coaches.index')
            ->with('success', 'Tenaga pendukung berhasil ditambahkan.');
    }

    public function decide(CoachDecisionRequest $request, CommunityCoach $coach): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);
        $this->assertBelongsToCommunity($community, $coach->community_id);
        DB::transaction(function () use ($community, $coach, $request): void {
            $community->newQuery()->whereKey($community->id)->where('is_active', true)->lockForUpdate()->firstOrFail();
            $coach = CommunityCoach::whereKey($coach->id)->lockForUpdate()->firstOrFail();
            if ($coach->status !== 'pending' || $coach->ended_at !== null) {
                throw ValidationException::withMessages(['action' => 'Pengajuan sudah diproses. Muat ulang daftar.']);
            }
            $approve = $request->validated('action') === 'approve';
            if ($approve) {
                abort_unless($coach->user()->where('is_active', true)->exists() && ($coach->personnelRole()->where('is_active', true)->exists() || ($coach->sports_personnel_role_id === null && SportsPersonnelRole::where('code', 'coach')->where('is_active', true)->exists())), 422, 'Akun dan peran harus aktif.');
            }
            $coach->update(['status' => $approve ? 'active' : 'rejected']);
        });

        return back()->with('success', 'Keputusan keanggotaan disimpan. Pemohon yang disetujui dapat kembali ke link untuk bergabung roster.');
    }

    public function destroy(Request $request, CommunityCoach $coach): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);
        $this->assertBelongsToCommunity($community, $coach->community_id);

        $coach->update(['status' => 'inactive', 'ended_at' => today()]);

        return redirect()
            ->route('panel.coaches.index')
            ->with('success', 'Keanggotaan tenaga pendukung diakhiri; riwayat tetap tersimpan.');
    }
}
