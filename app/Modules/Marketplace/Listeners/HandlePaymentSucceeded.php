<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Listeners;

use App\Contracts\Events\PaymentSucceeded;
use App\Modules\Marketplace\Models\Order;
use Illuminate\Support\Facades\DB;

class HandlePaymentSucceeded
{
    public function handle(PaymentSucceeded $event): void
    {
        if ($event->payableType !== 'order') {
            return;
        }

        DB::transaction(function () use ($event): void {
            /** @var Order|null $order */
            $order = Order::where('id', $event->payableId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return;
            }

            if ($order->status === 'pending') {
                $order->update([
                    'status' => 'paid',
                    'transaction_id' => $event->transactionId,
                ]);
            } elseif ($order->transaction_id === null) {
                $order->update([
                    'transaction_id' => $event->transactionId,
                ]);
            }
        });
    }
}
