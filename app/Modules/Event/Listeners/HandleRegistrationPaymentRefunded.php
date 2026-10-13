<?php

declare(strict_types=1);

namespace App\Modules\Event\Listeners;

use App\Contracts\Events\PaymentRefunded;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Support\Facades\DB;

class HandleRegistrationPaymentRefunded
{
    public function handle(PaymentRefunded $event): void
    {
        if ($event->payableType !== 'event_participant') {
            return;
        }

        DB::transaction(function () use ($event): void {
            $transaction = Transaction::query()
                ->whereKey($event->transactionId)
                ->where('payable_type', 'event_participant')
                ->where('payable_id', $event->payableId)
                ->lockForUpdate()
                ->first();

            if (! $transaction || $transaction->status !== 'refunded') {
                return;
            }

            /** @var EventParticipant|null $participant */
            $participant = EventParticipant::query()
                ->whereKey($event->payableId)
                ->whereNotIn('status', ['cancelled', 'attended'])
                ->lockForUpdate()
                ->first();

            if ($participant) {
                $participant->update(['status' => 'cancelled']);
            }
        });
    }
}
