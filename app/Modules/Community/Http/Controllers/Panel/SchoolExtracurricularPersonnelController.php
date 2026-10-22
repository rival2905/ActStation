<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\ExtracurricularPersonnelRequest;
use App\Modules\Community\Models\SchoolExtracurricularAppointment;
use App\Modules\Community\Services\SchoolExtracurricularPersonnelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolExtracurricularPersonnelController extends Controller
{
    public function __construct(
        protected SchoolExtracurricularPersonnelService $service
    ) {}

    public function index(Request $request, int|string $community, int|string $extracurricular): View
    {
        $user = $request->user();
        $unit = $this->service->scope($user, (int) $community, (int) $extracurricular);
        $this->service->authorize($unit, $user);

        $data = $this->service->indexData($unit, $user);

        return view('community.extracurricular.personnel.index', $data);
    }

    public function invite(ExtracurricularPersonnelRequest $request, int|string $community, int|string $extracurricular): RedirectResponse
    {
        $user = $request->user();
        $unit = $this->service->scope($user, (int) $community, (int) $extracurricular);
        $this->service->authorize($unit, $user);

        $result = $this->service->invite($unit, $user, $request->validated());

        return back()
            ->with('success', 'Undangan personel berhasil dibuat.')
            ->with('invite_url', $result['url']);
    }

    public function showAccept(Request $request, int|string $community, int|string $extracurricular, SchoolExtracurricularAppointment $appointment): View
    {
        $user = $request->user();
        $unit = $this->service->scope($user, (int) $community, (int) $extracurricular);
        $token = $request->query('token', '');
        abort_unless(is_string($token) && strlen($token) === 64, 403);

        $data = $this->service->showAccept($unit, $appointment, $user, $token);

        return view('community.extracurricular.personnel.index', array_merge($data, ['acceptMode' => true]));
    }

    public function accept(ExtracurricularPersonnelRequest $request, int|string $community, int|string $extracurricular, SchoolExtracurricularAppointment $appointment): RedirectResponse
    {
        $validated = $request->validated();
        $token = (string) ($validated['token'] ?? '');

        $user = $request->user();
        $unit = $this->service->scope($user, (int) $community, (int) $extracurricular);

        $this->service->accept($unit, $appointment, $user, $token);

        return redirect()
            ->route('panel.my-communities.index')
            ->with('success', 'Penugasan berhasil diterima.');
    }

    public function revoke(Request $request, int|string $community, int|string $extracurricular, SchoolExtracurricularAppointment $appointment): RedirectResponse
    {
        $user = $request->user();
        $unit = $this->service->scope($user, (int) $community, (int) $extracurricular);

        $this->service->revoke($unit, $appointment, $user);

        return back()->with('success', 'Undangan personel berhasil dibatalkan.');
    }
}
