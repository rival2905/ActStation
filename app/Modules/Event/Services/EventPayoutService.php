<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\EventPayout;
use App\Modules\Event\Models\Game;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EventPayoutService
{
    /**
     * Hitung total dana escrow yang terkumpul dari registrasi dan tiket event.
     *
     * @return array{
     *     registration_income: float,
     *     ticket_income: float,
     *     total_escrow: float,
     *     term1_max: float,
     *     term2_max: float,
     *     term1_paid: float,
     *     term2_paid: float,
     *     remaining_escrow: float
     * }
     */
    public function getEscrowSummary(Event $event): array
    {
        $participantIds = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('payment_channel', 'gateway_escrow')
            ->where('is_escrow_waived', false)
            ->pluck('id');

        $registrationIncome = (float) DB::table('transactions')
            ->where('type', 'registration')
            ->where('payable_type', 'event_participant')
            ->whereIn('payable_id', $participantIds)
            ->where('status', 'paid')
            ->sum('amount');

        // Dana tiket penonton
        $ticketCategoryIds = DB::table('ticket_categories')
            ->where('event_id', $event->id)
            ->pluck('id');

        $ticketIds = DB::table('tickets')
            ->whereIn('ticket_category_id', $ticketCategoryIds)
            ->pluck('id');

        $ticketIncome = (float) DB::table('transactions')
            ->where('type', 'ticket')
            ->whereIn('payable_id', $ticketIds)
            ->where('status', 'paid')
            ->sum('amount');

        $totalEscrow = $registrationIncome + $ticketIncome;
        $term1Max = round($totalEscrow * 0.40, 2);
        $term2Max = round($totalEscrow * 0.60, 2);

        $term1Paid = (float) EventPayout::query()
            ->where('event_id', $event->id)
            ->where('term', 1)
            ->whereIn('status', ['transferred', 'approved'])
            ->sum('amount');

        $term2Paid = (float) EventPayout::query()
            ->where('event_id', $event->id)
            ->where('term', 2)
            ->whereIn('status', ['transferred', 'approved'])
            ->sum('amount');

        $remainingEscrow = max(0.0, $totalEscrow - ($term1Paid + $term2Paid));

        return [
            'registration_income' => $registrationIncome,
            'ticket_income' => $ticketIncome,
            'total_escrow' => $totalEscrow,
            'term1_max' => $term1Max,
            'term2_max' => $term2Max,
            'term1_paid' => $term1Paid,
            'term2_paid' => $term2Paid,
            'remaining_escrow' => $remainingEscrow,
        ];
    }

    public function canRequestTerm1(Event $event): bool
    {
        if (! in_array($event->status, ['ongoing', 'completed'], true)) {
            return false;
        }

        // Minimal 1 game berlangsung (live) atau selesai (finished)
        $hasActiveGame = Game::query()
            ->where('event_id', $event->id)
            ->whereIn('game_status', ['live', 'finished'])
            ->exists();

        if (! $hasActiveGame) {
            return false;
        }
        // Cek belum ada payout term 1 requested/approved/transferred
        $hasTerm1 = EventPayout::query()
            ->where('event_id', $event->id)
            ->where('term', 1)
            ->whereIn('status', ['requested', 'approved', 'transferred'])
            ->exists();

        return ! $hasTerm1;
    }

    public function canRequestTerm2(Event $event): bool
    {
        if ($event->status !== 'completed') {
            return false;
        }

        // Lewat masa tunggu H+2 (48 jam)
        if ($event->ends_at !== null && Carbon::now()->lessThan($event->ends_at->copy()->addHours(48))) {
            return false;
        }

        $hasTerm2 = EventPayout::query()
            ->where('event_id', $event->id)
            ->where('term', 2)
            ->whereIn('status', ['requested', 'approved', 'transferred'])
            ->exists();

        return ! $hasTerm2;
    }

    /**
     * @param  array{
     *     bank_name: string,
     *     bank_account_number: string,
     *     bank_account_holder: string,
     *     notes?: string|null
     * }  $bankData
     */
    public function requestPayout(Event $event, User $user, int $term, array $bankData): EventPayout
    {
        $summary = $this->getEscrowSummary($event);

        if ($term === 1) {
            if (! $this->canRequestTerm1($event)) {
                throw new InvalidArgumentException('Syarat pencairan Termin 1 belum terpenuhi (event harus ongoing dan minimal 1 game dimulai).');
            }
            $amount = $summary['term1_max'];
            $fee = 0.00;
            $net = $amount;
            $payoutStatus = 'term1_requested';
        } elseif ($term === 2) {
            if (! $this->canRequestTerm2($event)) {
                throw new InvalidArgumentException('Syarat pelunasan Termin 2 belum terpenuhi (event harus completed dan melewati masa sanggah H+2).');
            }
            // 5% platform fee dari total omzet event
            $fee = round($summary['total_escrow'] * 0.05, 2);
            $amount = $summary['term2_max'];
            $net = max(0.0, $amount - $fee);
            $payoutStatus = 'term2_requested';
        } else {
            throw new InvalidArgumentException("Term [{$term}] tidak valid.");
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Tidak ada dana escrow yang dapat dicairkan.');
        }

        return DB::transaction(function () use ($event, $user, $term, $amount, $fee, $net, $bankData, $payoutStatus): EventPayout {
            $payout = EventPayout::create([
                'event_id' => $event->id,
                'term' => $term,
                'amount' => $amount,
                'platform_fee' => $fee,
                'net_amount' => $net,
                'bank_name' => $bankData['bank_name'],
                'bank_account_number' => $bankData['bank_account_number'],
                'bank_account_holder' => $bankData['bank_account_holder'],
                'status' => 'requested',
                'requested_by' => $user->id,
                'notes' => $bankData['notes'] ?? null,
            ]);

            $event->update(['payout_status' => $payoutStatus]);

            return $payout;
        });
    }

    public function processTransfer(
        EventPayout $payout,
        User $admin,
        ?string $transferProof = null,
        ?string $notes = null
    ): EventPayout {
        if ($payout->event?->status === 'cancelled') {
            throw new InvalidArgumentException('Pencairan dana dibatalkan: event berstatus cancelled.');
        }

        if ($payout->status !== 'requested' && $payout->status !== 'approved') {
            throw new InvalidArgumentException("Status payout saat ini ({$payout->status}) tidak dapat ditransfer.");
        }

        return DB::transaction(function () use ($payout, $admin, $transferProof, $notes): EventPayout {
            $payout->update([
                'status' => 'transferred',
                'approved_by' => $admin->id,
                'transfer_proof' => $transferProof ?? $payout->transfer_proof,
                'transferred_at' => now(),
                'notes' => $notes ?? $payout->notes,
            ]);

            $event = $payout->event;
            if ($payout->term === 1) {
                $event->update(['payout_status' => 'term1_paid']);
            } elseif ($payout->term === 2) {
                $event->update(['payout_status' => 'settled']);
            }

            return $payout;
        });
    }
}
