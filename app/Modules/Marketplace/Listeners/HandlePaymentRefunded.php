<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Listeners;

use App\Contracts\Events\PaymentRefunded;
use App\Modules\Marketplace\Models\Order;
use App\Modules\Marketplace\Models\OrderItem;
use App\Modules\Marketplace\Models\Product;
use Illuminate\Support\Facades\DB;

class HandlePaymentRefunded
{
    public function handle(PaymentRefunded $event): void
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

            // Idempotency guard: do not return stock repeatedly if already refunded or cancelled
            if (in_array($order->status, ['refunded', 'cancelled'], true)) {
                if ($order->status !== 'refunded') {
                    $order->update(['status' => 'refunded']);
                }

                return;
            }

            $order->update(['status' => 'refunded']);

            // Restore stocks
            $items = OrderItem::where('order_id', $order->id)->get();
            foreach ($items as $item) {
                /** @var Product|null $product */
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($product) {
                    $product->increment('stock', $item->quantity);
                    $product->refresh();

                    if ($product->status === 'out_of_stock' && $product->stock > 0) {
                        $product->update(['status' => 'active']);
                    }
                }
            }
        });
    }
}
