<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Contracts\Events\PaymentSucceeded;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Transaction::query()->with(['user:id,name', 'paymentMethod']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['payment_method_id'])) {
            $query->where('payment_method_id', $filters['payment_method_id']);
        } elseif (! empty($filters['metode'])) {
            $metode = (string) $filters['metode'];
            $query->whereHas('paymentMethod', fn ($q) => $q->where('code', $metode));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['periode'])) {
            match ($filters['periode']) {
                'today' => $query->whereDate('created_at', now()->today()),
                'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'month' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
                default => null,
            };
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('gateway_ref', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function markPaid(Transaction|int $transaction, ?string $gatewayRef = null, ?array $meta = null): Transaction
    {
        $id = $transaction instanceof Transaction ? $transaction->id : $transaction;

        return DB::transaction(function () use ($id, $gatewayRef, $meta): Transaction {
            $record = Transaction::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($record->status === 'paid') {
                return $record;
            }

            $updates = [
                'status' => 'paid',
                'paid_at' => now(),
            ];

            if ($gatewayRef !== null) {
                $updates['gateway_ref'] = $gatewayRef;
            }

            if ($meta !== null) {
                $updates['meta'] = array_merge($record->meta ?? [], $meta);
            }

            $record->update($updates);

            $snapshot = $record->fresh();

            DB::afterCommit(function () use ($snapshot): void {
                event(new PaymentSucceeded(
                    transactionId: (int) $snapshot->id,
                    payableType: (string) $snapshot->payable_type,
                    payableId: (int) $snapshot->payable_id,
                    amount: (string) $snapshot->amount,
                ));
            });

            return $snapshot;
        });
    }
}
