<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Contracts\Events\PaymentRefunded;
use App\Modules\Payment\Models\Refund;
use App\Modules\Payment\Models\Transaction;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RefundService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Refund::query()->with([
            'transaction.user:id,name',
            'user:id,name',
            'processor:id,name',
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->whereHas('transaction', function ($q) use ($search): void {
                $q->where('invoice_number', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function process(Refund $refund, string $action, ?int $processedBy = null): Refund
    {
        if (! in_array($action, ['approve', 'reject', 'process'], true)) {
            throw new DomainException("Aksi refund '{$action}' tidak valid.");
        }

        if ($action === 'approve') {
            return DB::transaction(function () use ($refund, $processedBy): Refund {
                $lockedRefund = Refund::where('id', $refund->id)->lockForUpdate()->firstOrFail();

                if ($lockedRefund->status !== 'requested') {
                    throw new DomainException("Refund dengan status '{$lockedRefund->status}' tidak dapat di-approve.");
                }

                $transaction = Transaction::where('id', $lockedRefund->transaction_id)->lockForUpdate()->firstOrFail();

                $alreadyCommittedCents = $this->toCents(
                    Refund::where('transaction_id', $transaction->id)
                        ->whereIn('status', ['approved', 'processed'])
                        ->where('id', '!=', $lockedRefund->id)
                        ->sum('amount')
                );

                $remainingCents = $this->toCents($transaction->total) - $alreadyCommittedCents;
                $refundAmountCents = $this->toCents($lockedRefund->amount);

                if ($refundAmountCents > $remainingCents) {
                    $remaining = bcdiv((string) $remainingCents, '100', 2);
                    throw new DomainException("Jumlah refund ({$lockedRefund->amount}) melebihi sisa transaksi ({$remaining}).");
                }
                $lockedRefund->update([
                    'status' => 'approved',
                    'processed_by' => $processedBy,
                ]);

                $refund->refresh();

                return $lockedRefund->fresh();
            });
        }

        if ($action === 'reject') {
            if ($refund->status !== 'requested') {
                throw new DomainException("Refund dengan status '{$refund->status}' tidak dapat di-reject.");
            }

            $refund->update([
                'status' => 'rejected',
                'processed_by' => $processedBy,
                'processed_at' => now(),
            ]);
            $refund->refresh();

            return $refund->fresh();
        }

        // $action === 'process'
        if ($refund->refresh()->status !== 'approved') {
            throw new DomainException("Refund harus berstatus 'approved' sebelum diproses. Status saat ini: '{$refund->status}'.");
        }

        return DB::transaction(function () use ($refund, $processedBy): Refund {
            $lockedRefund = Refund::where('id', $refund->id)->lockForUpdate()->firstOrFail();

            if ($lockedRefund->status !== 'approved') {
                throw new DomainException('Status refund berubah, tidak dapat diproses.');
            }

            $transaction = Transaction::where('id', $lockedRefund->transaction_id)->lockForUpdate()->firstOrFail();

            $previouslyCommittedCents = $this->toCents(
                Refund::where('transaction_id', $transaction->id)
                    ->whereIn('status', ['approved', 'processed'])
                    ->where('id', '!=', $lockedRefund->id)
                    ->sum('amount')
            );

            $remainingCents = $this->toCents($transaction->total) - $previouslyCommittedCents;
            $refundAmountCents = $this->toCents($lockedRefund->amount);

            if ($refundAmountCents > $remainingCents) {
                $remaining = bcdiv((string) $remainingCents, '100', 2);
                throw new DomainException("Jumlah refund ({$lockedRefund->amount}) melebihi sisa transaksi ({$remaining}).");
            }
            $lockedRefund->update([
                'status' => 'processed',
                'processed_by' => $processedBy,
                'processed_at' => now(),
            ]);

            $totalProcessedCents = $this->toCents(
                Refund::where('transaction_id', $transaction->id)
                    ->where('status', 'processed')
                    ->sum('amount')
            );

            $isFullRefund = false;
            if ($totalProcessedCents >= $this->toCents($transaction->total)) {
                $transaction->update(['status' => 'refunded']);
                $isFullRefund = true;
            }

            $refundSnapshot = $lockedRefund->fresh();
            $transactionSnapshot = $transaction->fresh();

            // ponytail: partial refund only returns money without ticket cancellation; upgrade path: item-level refund with dedicated event when per-item ticket cancellation is supported.
            if ($isFullRefund) {
                DB::afterCommit(function () use ($transactionSnapshot, $refundSnapshot): void {
                    event(new PaymentRefunded(
                        transactionId: (int) $transactionSnapshot->id,
                        refundId: (int) $refundSnapshot->id,
                        payableType: (string) $transactionSnapshot->payable_type,
                        payableId: (int) $transactionSnapshot->payable_id,
                        amount: (string) $refundSnapshot->amount,
                    ));
                });
            }

            $refund->refresh();

            return $refundSnapshot;
        });
    }

    private function toCents(mixed $amount): int
    {
        return (int) bcmul((string) ($amount ?? '0'), '100', 0);
    }
}
