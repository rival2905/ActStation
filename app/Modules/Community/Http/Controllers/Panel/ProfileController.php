<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Contracts\SchoolLookup;
use App\Modules\Community\Http\Requests\Panel\PanelProfileRequest;
use App\Modules\Community\Http\Requests\Panel\StoreCommunityRequest;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Services\CommunityLifecycle;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class ProfileController extends PanelBaseController
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $hasOfficial = CommunityOfficial::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->exists();

        if ($hasOfficial) {
            return redirect()
                ->route('panel.profile.show')
                ->with('info', 'Anda sudah terdaftar sebagai pengurus aktif komunitas.');
        }

        $sportCategories = DB::table('sport_categories')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('panel.community.create', compact('sportCategories'));
    }

    public function store(StoreCommunityRequest $request, SchoolLookup $schoolLookup): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $alreadyOfficial = CommunityOfficial::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->exists();

        if ($alreadyOfficial) {
            abort(403, 'Anda sudah terdaftar sebagai pengurus aktif komunitas.');
        }

        $data = $request->validated();

        if (in_array($data['subtype'], ['school_sd', 'school_smp', 'school_sma', 'school'], true)) {
            $data['subtype'] = 'school';
            if (! empty($data['npsn'])) {
                $masterSchool = $schoolLookup->findByNpsn((string) $data['npsn']);
                if ($masterSchool) {
                    $data['name'] = $masterSchool->name;
                    $userProvidedCoords = ($request->has('latitude') && ($data['latitude'] ?? null) !== null)
                        || ($request->has('longitude') && ($data['longitude'] ?? null) !== null);
                    if (! $userProvidedCoords && $masterSchool->latitude !== null && $masterSchool->longitude !== null) {
                        $data['latitude'] = $masterSchool->latitude;
                        $data['longitude'] = $masterSchool->longitude;
                    }
                }
            }
        }

        DB::transaction(function () use ($data, $user, $request): Community {
            if ($request->hasFile('logo')) {
                $data['logo'] = $request->file('logo')->store('community_logos', 'public');
            }

            $slug = Str::slug($data['name']);
            $originalSlug = $slug;
            $count = 1;
            while (Community::where('slug', $slug)->exists()) {
                $slug = "{$originalSlug}-{$count}";
                $count++;
            }
            $data['slug'] = $slug;
            $data['status'] = 'draft';
            $data['is_active'] = true;

            $community = Community::create($data);

            CommunityOfficial::create([
                'community_id' => $community->id,
                'user_id' => $user->id,
                'position' => 'manager',
                'status' => 'active',
                'started_at' => now()->toDateString(),
                'ended_at' => null,
            ]);

            if (! $user->hasRole('community_manager')) {
                $user->assignRole('community_manager');
            }

            return $community;
        });

        return redirect()
            ->route('panel.profile.show')
            ->with('success', 'Komunitas berhasil didaftarkan! Silakan lengkapi pengurus, pelatih, roster atlet, dan dokumen legalitas.');
    }

    public function show(Request $request, SchoolLookup $schoolLookup): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $hasOfficial = CommunityOfficial::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->exists();

        if (! $hasOfficial && ! $user->hasRole('superadmin') && ! $user->hasRole('admin')) {
            return redirect()
                ->route('panel.community.create')
                ->with('info', 'Silakan daftarkan komunitas Anda terlebih dahulu.');
        }

        $community = $this->currentCommunity($request);
        $this->authorize('view', $community);

        $community->loadMissing(['documents', 'officials.user']);

        $derivedSubtype = $community->subtype;
        if ($community->subtype === 'school' && $community->npsn) {
            $masterSchool = $schoolLookup->findByNpsn($community->npsn);
            $derivedSubtype = $schoolLookup->deriveSubtypeToken($masterSchool?->school_level) ?? 'school_sd';
        }

        $sportCategories = DB::table('sport_categories')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $activities = $community->activities()
            ->leftJoin('sport_categories', 'community_activities.sport_category_id', '=', 'sport_categories.id')
            ->select('community_activities.*', 'sport_categories.name as sport_name')
            ->orderBy('community_activities.id')
            ->get();

        $activitySportCategories = $sportCategories;

        return view('panel.profile', compact('community', 'sportCategories', 'derivedSubtype', 'activities', 'activitySportCategories'));
    }

    public function update(PanelProfileRequest $request, SchoolLookup $schoolLookup): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        $data = $request->validated();

        if (isset($data['subtype']) && in_array($data['subtype'], ['school_sd', 'school_smp', 'school_sma', 'school'], true)) {
            $data['subtype'] = 'school';
            if (! empty($data['npsn'])) {
                $masterSchool = $schoolLookup->findByNpsn((string) $data['npsn']);
                if ($masterSchool) {
                    $data['name'] = $masterSchool->name;
                }
            }
        }

        if (! $request->has('latitude') && ! $request->has('longitude')) {
            unset($data['latitude'], $data['longitude']);
        }

        if ($request->hasFile('logo')) {
            if ($community->logo && Storage::disk('public')->exists($community->logo)) {
                Storage::disk('public')->delete($community->logo);
            }
            $data['logo'] = $request->file('logo')->store('community_logos', 'public');
        }

        $community->update($data);

        return redirect()
            ->route('panel.profile.show')
            ->with('success', 'Profil komunitas berhasil diperbarui.');
    }

    public function requestVerification(Request $request, CommunityLifecycle $lifecycle): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        try {
            $lifecycle->submit($community);

            return redirect()
                ->route('panel.profile.show')
                ->with('success', 'Permohonan verifikasi komunitas berhasil diajukan.');
        } catch (DomainException|InvalidArgumentException $e) {
            return redirect()
                ->route('panel.profile.show')
                ->with('error', $e->getMessage());
        }
    }
}
