<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Event\Models\EventPayout;
use App\Modules\Event\Services\EventPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class AdminEscrowPayoutController extends Controller
{
    public function __construct(
        private readonly EventPayoutService $payoutService,
    ) {}

    public function index(): View
    {
        $payouts = EventPayout::query()
            ->with(['event', 'requester', 'approver'])
            ->latest()
            ->paginate(20);

        return view('admin.events.escrow_payouts.index', compact('payouts'));
    }

    public function processTransfer(Request $request, EventPayout $payout): RedirectResponse
    {
        $validated = $request->validate([
            'transfer_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $proofPath = null;
        if ($request->hasFile('transfer_proof')) {
            $proofPath = $request->file('transfer_proof')->store('payout_proofs', 'public');
        }

        try {
            /** @var User $admin */
            $admin = Auth::user();

            $this->payoutService->processTransfer(
                payout: $payout,
                admin: $admin,
                transferProof: $proofPath,
                notes: $validated['notes'] ?? null
            );

            return back()->with('success', "Pencairan dana Termin {$payout->term} untuk event [{$payout->event?->title}] berhasil dikonfirmasi ditransfer.");
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
