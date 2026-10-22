<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Contracts\SchoolLookup;
use App\Modules\Community\Http\Requests\Panel\PanelProfileRequest;
use App\Modules\Community\Http\Requests\Panel\StoreCommunityRequest;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolRegistration;
use App\Modules\Community\Services\CommunityLifecycle;
use App\Modules\Community\Services\SchoolOnboardingService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

        // ponytail: pending/rejected school registration resumes existing flow
        $pendingSchool = SchoolRegistration::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'rejected'])
            ->latest()
            ->first();

        if ($pendingSchool) {
            return redirect()
                ->route('panel.school-registrations.show', $pendingSchool->id)
                ->with('info', 'Sekolah sudah tersimpan. Selesaikan verifikasi email dan tunggu pemeriksaan admin.');
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

        if ($data['subtype'] === 'school') {
            $registration = app(SchoolOnboardingService::class)->registerSchool($user, $data);

            return redirect()->route('panel.school-registrations.show', $registration->id)->with('success', 'Sekolah tersimpan. Konfirmasi email resmi dan unggah bukti kewenangan untuk mengaktifkan pengelolaan.');
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
            // ponytail: pending/rejected school registration resumes existing flow
            $pendingSchool = SchoolRegistration::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'rejected'])
                ->latest()
                ->first();

            if ($pendingSchool) {
                return redirect()
                    ->route('panel.school-registrations.show', $pendingSchool->id)
                    ->with('info', 'Sekolah sudah tersimpan. Selesaikan verifikasi email dan tunggu pemeriksaan admin.');
            }

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
        $schoolExtracurriculars = $community->subtype === 'school'
            ? DB::table('school_extracurriculars')->join('sport_categories', 'sport_categories.id', '=', 'school_extracurriculars.sport_category_id')
                ->where('school_extracurriculars.community_id', $community->id)
                ->select('school_extracurriculars.id', 'sport_categories.name as sport_name')->orderBy('sport_categories.name')->get()
            : collect();

        $schoolRegistration = $community->subtype === 'school'
            ? SchoolRegistration::where('community_id', $community->id)->latest('id')->first()
            : null;
        $workspace = $schoolRegistration ? app(SchoolOnboardingService::class)->authorityWorkspace($schoolRegistration, $user) : [];
        $canChangeSchoolEmail = $workspace['canTransfer'] ?? false;
        $pendingChange = $workspace['pendingChange'] ?? null;
        $pendingEmailChange = $pendingChange
            && (int) $pendingChange->requested_by === (int) $pendingChange->target_user_id
            && $pendingChange->target_email === $pendingChange->new_email ? $pendingChange : null;

        return view('panel.profile', compact('community', 'sportCategories', 'derivedSubtype', 'activities', 'activitySportCategories', 'schoolExtracurriculars', 'schoolRegistration', 'canChangeSchoolEmail', 'pendingEmailChange'));
    }

    public function update(PanelProfileRequest $request, SchoolLookup $schoolLookup): RedirectResponse
    {
        $community = $this->currentCommunity($request);
        $this->authorize('update', $community);

        if (SchoolRegistration::where('community_id', $community->id)->exists()) {
            $errors = [];

            if ($request->has('contact_email') && (string) $request->input('contact_email') !== (string) $community->contact_email) {
                $errors['contact_email'] = 'Email kontak sekolah terdaftar tidak dapat diubah.';
            }

            if ($request->has('npsn') && (string) $request->input('npsn') !== (string) $community->npsn) {
                $errors['npsn'] = 'NPSN sekolah terdaftar tidak dapat diubah.';
            }

            if ($request->has('subtype')) {
                $postedSubtype = (string) $request->input('subtype');
                $normalizedSubtype = in_array($postedSubtype, ['school_sd', 'school_smp', 'school_sma', 'school'], true) ? 'school' : $postedSubtype;
                if ($normalizedSubtype !== 'school') {
                    $errors['subtype'] = 'Tipe sekolah tidak dapat diubah.';
                }
            }

            if ($request->has('sport_category_id')) {
                $reqSport = $request->input('sport_category_id');
                $currentSport = $community->sport_category_id !== null ? (int) $community->sport_category_id : null;
                $newSport = ($reqSport !== null && $reqSport !== '') ? (int) $reqSport : null;
                if ($newSport !== $currentSport) {
                    $errors['sport_category_id'] = 'Cabang olahraga utama sekolah terdaftar tidak dapat diubah.';
                }
            }

            if (! empty($errors)) {
                throw ValidationException::withMessages($errors);
            }
        }

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
