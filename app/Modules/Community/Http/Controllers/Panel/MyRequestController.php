<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Services\AthleteMembershipService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyRequestController extends Controller
{
    public function __construct(
        protected AthleteMembershipService $membershipService
    ) {}

    public function index(Request $request): View
    {
        $requests = CommunityAthlete::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->with('community')
            ->latest()
            ->get();

        return view('panel.my-requests.index', compact('requests'));
    }

    public function approve(Request $request, CommunityAthlete $athlete): RedirectResponse
    {
        try {
            $this->membershipService->approveByAthlete($athlete, $request->user());
        } catch (DomainException $e) {
            return redirect()
                ->route('panel.my-requests.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('panel.my-requests.index')
            ->with('success', 'Permohonan bergabung berhasil disetujui.');
    }

    public function reject(Request $request, CommunityAthlete $athlete): RedirectResponse
    {
        try {
            $this->membershipService->rejectByAthlete($athlete, $request->user());
        } catch (DomainException $e) {
            return redirect()
                ->route('panel.my-requests.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('panel.my-requests.index')
            ->with('success', 'Permohonan bergabung ditolak.');
    }
}
