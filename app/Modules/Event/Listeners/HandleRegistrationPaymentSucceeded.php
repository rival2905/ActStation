<?php

declare(strict_types=1);

namespace App\Modules\Event\Listeners;

use App\Contracts\Events\PaymentSucceeded;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Support\Facades\DB;

class HandleRegistrationPaymentSucceeded
{
    public function handle(PaymentSucceeded $event): void
    {
        if ($event->payableType !== 'event_participant') {
            return;
        }

        DB::transaction(function () use ($event): void {
            /** @var Transaction|null $transaction */
            $transaction = Transaction::query()
                ->whereKey($event->transactionId)
                ->where('payable_type', 'event_participant')
                ->where('payable_id', $event->payableId)
                ->where('status', 'paid')
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                return;
            }

            /** @var EventParticipant|null $participant */
            $participant = EventParticipant::query()
                ->with(['category', 'event'])
                ->whereKey($event->payableId)
                ->lockForUpdate()
                ->first();

            if (! $participant || $participant->status !== 'pending_payment') {
                return;
            }

            $paidAt = $transaction->paid_at;
            if ($paidAt === null
                || ($transaction->expired_at !== null && $paidAt->greaterThan($transaction->expired_at))) {
                return;
            }

            $category = $participant->category;
            $flow = (string) ($category?->registration_flow ?? 'screening_first');
            if (in_array($flow, ['payment_first', 'by_invitation'], true)
                && ($category?->payment_start === null || $category?->payment_end === null
                    || $paidAt->lessThan($category->payment_start) || $paidAt->greaterThan($category->payment_end))) {
                return;
            }

            if ($flow === 'screening_first'
                && $participant->event?->starts_at !== null
                && $paidAt->greaterThan($participant->event->starts_at)) {
                return;
            }

            $flow = (string) ($participant->category?->registration_flow ?? 'screening_first');
            $participant->update([
                'status' => $flow === 'screening_first' ? 'confirmed' : 'registered',
                'payment_verified_at' => $transaction->paid_at ?? now(),
                'revision_notes' => null,
            ]);
        });
    }
}
