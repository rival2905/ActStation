<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CommunityCheckoutController extends Controller
{
    public function show(EventParticipant $participant): View|RedirectResponse
    {
        $this->authorizeParticipantAccess($participant);

        $participant->loadMissing(['event', 'category']);
        $event = $participant->event;
        $category = $participant->category;

        if (! $category || (float) $category->registration_fee <= 0) {
            return redirect()
                ->route('panel.community-events.roster', $participant->id)
                ->with('warning', 'Kategori gratis tidak memiliki tagihan pembayaran.');
        }

        $transaction = Transaction::query()
            ->where('payable_type', 'event_participant')
            ->where('payable_id', $participant->id)
            ->latest()
            ->first();

        if (! $transaction) {
            return redirect()
                ->route('panel.community-events.roster', $participant->id)
                ->with('warning', 'Tagihan belum diterbitkan. Pendaftaran harus lolos tahapan verifikasi yang berlaku.');
        }

        if ($participant->status !== 'pending_payment' || $transaction->status !== 'pending') {
            return redirect()
                ->route('panel.community-events.roster', $participant->id)
                ->with('warning', 'Tagihan ini tidak lagi dapat dibayar.');
        }

        $now = now();
        if ($transaction->expired_at !== null && $now->greaterThan($transaction->expired_at)) {
            return redirect()
                ->route('panel.community-events.roster', $participant->id)
                ->with('warning', 'Batas waktu pembayaran telah berakhir.');
        }

        if (in_array($category->registration_flow, ['payment_first', 'by_invitation'], true)
            && ($category->payment_start === null || $category->payment_end === null
                || $now->lessThan($category->payment_start) || $now->greaterThan($category->payment_end))) {
            return redirect()
                ->route('panel.community-events.roster', $participant->id)
                ->with('warning', 'Pembayaran hanya dapat dilakukan dalam jendela pembayaran kategori.');
        }

        return view('panel.registrations.checkout', compact('participant', 'event', 'category', 'transaction'));
    }

    public function payManual(Request $request, EventParticipant $participant): RedirectResponse
    {
        $this->authorizeParticipantAccess($participant);
        $participant->loadMissing(['event', 'category']);

        $transaction = Transaction::query()
            ->where('payable_type', 'event_participant')
            ->where('payable_id', $participant->id)
            ->where('status', 'pending')
            ->latest()
            ->first();
        $now = now();

        if (! $participant->event?->allow_manual_payment || $participant->status !== 'pending_payment' || ! $transaction) {
            return back()->with('error', 'Pembayaran manual tidak tersedia untuk pendaftaran ini.');
        }

        if (($transaction->expired_at !== null && $now->greaterThan($transaction->expired_at))
            || (in_array($participant->category?->registration_flow, ['payment_first', 'by_invitation'], true)
                && ($participant->category?->payment_start === null || $participant->category?->payment_end === null
                    || $now->lessThan($participant->category->payment_start) || $now->greaterThan($participant->category->payment_end)))) {
            return back()->with('error', 'Jendela pembayaran pendaftaran telah berakhir atau belum dibuka.');
        }

        $request->validate([
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'waiver_agreed' => ['required', 'accepted'],
        ], [
            'waiver_agreed.accepted' => 'Anda wajib menyetujui dan mencentang klausul pelepasan tanggung jawab sebelum mengunggah pembayaran manual.',
        ]);

        $proofPath = $request->file('payment_proof')->store('manual_payment_proofs', 'public');

        $participant->update([
            'payment_channel' => 'manual_organizer',
            'payment_proof' => $proofPath,
            'is_escrow_waived' => true,
            'waiver_signed_at' => now(),
            'waiver_ip' => $request->ip(),
            'waiver_user_agent' => (string) $request->userAgent(),
        ]);

        return redirect()
            ->route('panel.community-events.roster', $participant->id)
            ->with('success', 'Bukti transfer manual berhasil diunggah. Menunggu verifikasi dari panitia turnamen.');
    }

    private function authorizeParticipantAccess(EventParticipant $participant): void
    {
        $userId = Auth::id();
        if ($participant->user_id === $userId || Auth::user()?->hasRole('superadmin')) {
            return;
        }

        // Cek pengurus komunitas
        if ($participant->community_id) {
            $isOfficial = CommunityOfficial::query()
                ->where('community_id', $participant->community_id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->exists();

            if ($isOfficial) {
                return;
            }
        }

        abort(403, 'Anda tidak memiliki hak akses pada berkas pendaftaran ini.');
    }
}
