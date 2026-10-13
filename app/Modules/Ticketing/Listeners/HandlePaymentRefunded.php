<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Listeners;

use App\Contracts\Events\PaymentRefunded;
use App\Modules\Ticketing\Models\Ticket;
use App\Modules\Ticketing\Models\TicketCategory;
use Illuminate\Support\Facades\DB;

class HandlePaymentRefunded
{
    public function handle(PaymentRefunded $event): void
    {
        if ($event->payableType !== 'ticket') {
            return;
        }

        DB::transaction(function () use ($event): void {
            $tickets = Ticket::where('transaction_id', $event->transactionId)
                ->lockForUpdate()
                ->get();

            foreach ($tickets as $ticket) {
                if ($ticket->status === 'cancelled' || $ticket->status === 'used') {
                    // Idempotent: cancelled already processed; used tickets must not be cancelled nor quota returned
                    continue;
                }

                if ($ticket->status !== 'active' && $ticket->status !== 'pending') {
                    continue;
                }
                $ticket->update(['status' => 'cancelled']);

                /** @var TicketCategory|null $cat */
                $cat = TicketCategory::where('id', $ticket->ticket_category_id)
                    ->lockForUpdate()
                    ->first();

                if ($cat && $cat->sold_count > 0) {
                    $cat->decrement('sold_count');
                }
            }
        });
    }
}
