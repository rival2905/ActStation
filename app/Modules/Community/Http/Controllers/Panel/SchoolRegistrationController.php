<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\Panel\SchoolRegistrationRequest;
use App\Modules\Community\Models\SchoolRegistration;
use App\Modules\Community\Services\SchoolOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolRegistrationController extends Controller
{
    public function show(Request $request, int $registration, SchoolOnboardingService $service): View
    {
        abort_unless((bool) $request->user()?->is_active, 403);
        $record = $service->owned($registration, $request->user());

        return view('panel.school_registrations.show', [
            'registration' => $record,
            'resumeUrl' => $service->resolveEventResumptionUrl($registration),
            ...$service->authorityWorkspace($record, $request->user()),
        ]);
    }

    public function requestVerification(Request $request, int $registration, SchoolOnboardingService $service): RedirectResponse
    {
        abort_unless((bool) $request->user()?->is_active, 403);
        $service->requestCommunityVerification($registration, $request->user());

        return redirect()->route('panel.school-registrations.show', $registration)->with('success', 'Pengajuan verifikasi legalitas dikirim untuk pemeriksaan admin.');
    }

    public function verify(Request $request, int $registration, SchoolOnboardingService $service): View
    {
        abort_unless((bool) $request->user()?->is_active, 403);
        $record = $service->owned($registration, $request->user());

        return view('panel.school_registrations.verify', ['registration' => $record]);
    }

    public function consume(SchoolRegistrationRequest $request, int $registration, SchoolOnboardingService $service): RedirectResponse
    {
        $service->consumeEmailVerification($registration, $request->validated('token'), $request->user());

        return redirect()->route('panel.school-registrations.show', $registration)->with('success', 'Email sekolah terkonfirmasi. Menunggu pemeriksaan kewenangan admin.');
    }

    public function resend(Request $request, int $registration, SchoolOnboardingService $service): RedirectResponse
    {
        abort_unless((bool) $request->user()?->is_active, 403);
        $service->resendVerificationEmail($registration, $request->user());

        return back()->with('success', 'Permintaan pengiriman verifikasi dicatat. Periksa status pengiriman email bila belum diterima.');
    }

    public function evidence(SchoolRegistrationRequest $request, int $registration, SchoolOnboardingService $service): RedirectResponse
    {
        $service->storeEvidence($registration, $request->user(), $request->file('evidence'));

        return back()->with('success', 'Bukti kewenangan tersimpan untuk pemeriksaan admin.');
    }

    public function index(Request $request): View
    {
        abort_unless((bool) ($request->user()?->is_active && $request->user()->can('community.verify')), 403);

        return view('panel.school_registrations.review', [
            'registrations' => SchoolRegistration::latest()->paginate(20),
        ]);
    }

    public function review(SchoolRegistrationRequest $request, int $registration, SchoolOnboardingService $service): RedirectResponse
    {
        $service->reviewRegistration($registration, $request->user(), $request->validated('action') === 'approve', $request->validated('reason'));

        return back()->with('success', 'Keputusan kewenangan sekolah tersimpan.');
    }

    public function download(Request $request, int $registration, SchoolOnboardingService $service): mixed
    {
        return $service->downloadEvidence($registration, $request->user());
    }
}
