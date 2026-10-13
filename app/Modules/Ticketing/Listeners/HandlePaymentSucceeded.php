<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Listeners;

use App\Contracts\Events\PaymentSucceeded;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Support\Facades\DB;

class HandlePaymentSucceeded
{
    public function handle(PaymentSucceeded $event): void
    {
        if ($event->payableType !== 'ticket') {
            return;
        }

        DB::transaction(function () use ($event): void {
            $tickets = Ticket::where('transaction_id', $event->transactionId)
                ->lockForUpdate()
                ->get();

            foreach ($tickets as $ticket) {
                // Idempotent: used and cancelled tickets are untouched; only pending becomes active
                if ($ticket->status === 'used' || $ticket->status === 'cancelled') {
                    continue;
                }

                if ($ticket->status === 'pending') {
                    $ticket->update(['status' => 'active']);
                }
            }
        });
    }
}
