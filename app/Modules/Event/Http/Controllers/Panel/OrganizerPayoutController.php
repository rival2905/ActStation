<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventPayout;
use App\Modules\Event\Services\EventPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class OrganizerPayoutController extends Controller
{
    public function __construct(
        private readonly EventPayoutService $payoutService,
    ) {}

    public function index(Event $event): View
    {
        $this->authorizeOrganizer($event);

        $summary = $this->payoutService->getEscrowSummary($event);
        $canTerm1 = $this->payoutService->canRequestTerm1($event);
        $canTerm2 = $this->payoutService->canRequestTerm2($event);

        $payouts = EventPayout::query()
            ->where('event_id', $event->id)
            ->with(['requester', 'approver'])
            ->latest()
            ->get();

        $workspace = request()->routeIs('admin.*') ? 'admin' : 'organizer';

        return view('panel.organizer.payouts.index', compact('event', 'summary', 'canTerm1', 'canTerm2', 'payouts', 'workspace'));
    }

    public function requestPayout(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($event);

        $validated = $request->validate([
            'term' => ['required', 'integer', 'in:1,2'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User|null $user */
        $user = request()->routeIs('admin.*') ? $event->organizer : Auth::user();

        if (! $user || empty($user->bank_account_number) || empty($user->bank_name) || $user->kyc_status !== 'verified') {
            return back()->with('error', 'Rekening bank PIC terverifikasi belum tersedia. Lengkapi profil KYC organizer sebelum mengajukan payout.');
        }

        $bankData = [
            'bank_name' => (string) $user->bank_name,
            'bank_account_number' => (string) $user->bank_account_number,
            'bank_account_holder' => (string) ($user->bank_account_holder ?? $user->name),
            'notes' => $validated['notes'] ?? null,
        ];

        try {
            $term = (int) $validated['term'];
            $this->payoutService->requestPayout($event, $user, $term, $bankData);

            return back()->with('success', "Permohonan pencairan dana Termin {$term} berhasil diajukan ke admin platform.");
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function authorizeOrganizer(Event $event): void
    {
        if (! request()->routeIs('admin.*') && $event->user_id !== Auth::id() && ! Auth::user()?->hasRole('superadmin')) {
            abort(403, 'Akses ditolak.');
        }
    }
}
