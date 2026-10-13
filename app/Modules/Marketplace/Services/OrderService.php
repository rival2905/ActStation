<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Services;

use App\Models\User;
use App\Modules\Marketplace\Exceptions\OutOfStockException;
use App\Modules\Marketplace\Exceptions\ProductUnavailableException;
use App\Modules\Marketplace\Models\Order;
use App\Modules\Marketplace\Models\OrderItem;
use App\Modules\Marketplace\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrderService
{
    /**
     * @var array<string, list<string>>
     */
    public const ALLOWED_TRANSITIONS = [
        'pending' => ['paid', 'cancelled'],
        'paid' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
        'refunded' => [],
    ];

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function place(int|User $user, array $items, ?string $shippingAddress = null, ?string $notes = null): Order
    {
        if (empty($items)) {
            throw new InvalidArgumentException('Daftar item pesanan tidak boleh kosong.');
        }

        $userId = $user instanceof User ? $user->id : $user;

        return DB::transaction(function () use ($userId, $items, $shippingAddress, $notes): Order {
            // Sort items by product_id to prevent deadlocks
            usort($items, fn ($a, $b) => $a['product_id'] <=> $b['product_id']);

            $total = 0.0;
            $orderItemsData = [];

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];

                if ($quantity <= 0) {
                    throw new InvalidArgumentException('Kuantitas item harus lebih besar dari nol.');
                }

                /** @var Product $product */
                $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();
                if (! in_array($product->status, ['active', 'out_of_stock'], true)) {
                    throw new ProductUnavailableException("Produk '{$product->name}' tidak aktif.");
                }

                $product->loadMissing('merchant');
                if (! $product->merchant || $product->merchant->status !== 'active') {
                    throw new ProductUnavailableException("Merchant untuk produk '{$product->name}' tidak aktif.");
                }

                if ($product->status === 'out_of_stock' || $product->stock < $quantity) {
                    throw new OutOfStockException("Stok produk '{$product->name}' tidak mencukupi (tersedia: {$product->stock}, diminta: {$quantity}).");
                }

                $product->decrement('stock', $quantity);
                $product->refresh();

                if ($product->stock === 0) {
                    $product->update(['status' => 'out_of_stock']);
                }

                // Price is taken directly from the database, never from input
                $price = (float) $product->price;
                $subtotal = round($price * $quantity, 2);
                $total += $subtotal;

                $orderItemsData[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                ];
            }

            $orderNumber = $this->generateOrderNumber();

            /** @var Order $order */
            $order = Order::create([
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'status' => 'pending',
                'total' => round($total, 2),
                'shipping_address' => $shippingAddress,
                'notes' => $notes,
            ]);

            foreach ($orderItemsData as $itemData) {
                $itemData['order_id'] = $order->id;
                OrderItem::create($itemData);
            }

            return $order->load('items.product');
        });
    }

    public function cancel(Order|int $order): Order
    {
        $orderId = $order instanceof Order ? $order->id : $order;

        return DB::transaction(function () use ($orderId): Order {
            /** @var Order $orderRecord */
            $orderRecord = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();

            // Idempotency guard: do not return stock repeatedly if already cancelled or refunded
            if (in_array($orderRecord->status, ['cancelled', 'refunded'], true)) {
                return $orderRecord;
            }

            if (! in_array('cancelled', self::ALLOWED_TRANSITIONS[$orderRecord->status] ?? [], true)) {
                throw new InvalidArgumentException("Order dengan status '{$orderRecord->status}' tidak dapat dibatalkan.");
            }

            $orderRecord->update(['status' => 'cancelled']);

            // Restore product stocks
            $items = OrderItem::where('order_id', $orderRecord->id)->get();
            foreach ($items as $item) {
                /** @var Product|null $product */
                $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                if ($product) {
                    $product->increment('stock', $item->quantity);
                    $product->refresh();

                    if ($product->status === 'out_of_stock' && $product->stock > 0) {
                        $product->update(['status' => 'active']);
                    }
                }
            }

            return $orderRecord;
        });
    }

    public function updateStatus(Order|int $order, string $newStatus): Order
    {
        $orderId = $order instanceof Order ? $order->id : $order;

        return DB::transaction(function () use ($orderId, $newStatus): Order {
            /** @var Order $orderRecord */
            $orderRecord = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();

            if ($orderRecord->status === $newStatus) {
                return $orderRecord;
            }

            $allowed = self::ALLOWED_TRANSITIONS[$orderRecord->status] ?? [];
            if (! in_array($newStatus, $allowed, true)) {
                throw new InvalidArgumentException("Transisi status order dari '{$orderRecord->status}' ke '{$newStatus}' tidak diperbolehkan.");
            }

            if ($newStatus === 'cancelled') {
                return $this->cancel($orderRecord);
            }

            $orderRecord->update(['status' => $newStatus]);

            return $orderRecord;
        });
    }

    public function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
