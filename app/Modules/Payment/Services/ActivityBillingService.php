<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Contracts\ActivityBilling;
use App\Models\User;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivityBillingService implements ActivityBilling
{
    public function invoice(int $userId, int $registrationId, int $amount): array
    {
        if ($amount <= 0 || $amount > 999999999) {
            throw ValidationException::withMessages(['amount' => 'Nominal tagihan harus positif dan dalam batas transaksi.']);
        }

        return DB::transaction(function () use ($userId, $registrationId, $amount): array {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $record = $this->query($registrationId)->lockForUpdate()->first();
            if ($record) {
                $this->assertAmount($record, $amount);
                abort_unless((int) $record->user_id === $userId, 403);

                return $record->toArray();
            }

            return Transaction::create([
                'user_id' => $userId, 'type' => 'registration',
                'payable_type' => 'activity_registration', 'payable_id' => $registrationId,
                'amount' => (string) $amount, 'fee' => '0', 'total' => (string) $amount,
                'status' => 'pending', 'expired_at' => null, 'meta' => [],
            ])->toArray();
        });
    }

    public function find(int $registrationId): ?array
    {
        return $this->query($registrationId)->first()?->toArray();
    }

    public function paid(int $registrationId, int $amount): bool
    {
        $record = $this->query($registrationId)->first();
        if (! $record || $amount <= 0) {
            return false;
        }
        $this->assertAmount($record, $amount);

        return $record->status === 'paid';
    }

    public function confirm(int $registrationId, int $amount): void
    {
        DB::transaction(function () use ($registrationId, $amount): void {
            $record = $this->query($registrationId)->lockForUpdate()->firstOrFail();
            $this->assertAmount($record, $amount);
            if ($record->status === 'paid') {
                return;
            }
            if ($record->status !== 'pending' || empty($record->meta['payment_proof']) || empty($record->meta['is_escrow_waived'])) {
                throw ValidationException::withMessages(['payment' => 'Bukti transfer dan persetujuan non-escrow diperlukan.']);
            }
            app(TransactionService::class)->markPaid($record);
        });
    }

    public function recordManualProof(int $registrationId, array $proof): void
    {
        DB::transaction(function () use ($registrationId, $proof): void {
            $record = $this->query($registrationId)->lockForUpdate()->firstOrFail();
            if ($record->status !== 'pending') {
                throw ValidationException::withMessages(['payment' => 'Tagihan tidak dapat diubah.']);
            }
            $record->update(['meta' => array_merge($record->meta ?? [], $proof)]);
        });
    }

    private function query(int $registrationId): Builder
    {
        return Transaction::where('payable_type', 'activity_registration')->where('payable_id', $registrationId);
    }

    private function assertAmount(Transaction $record, int $amount): void
    {
        if ($amount <= 0 || (string) $record->amount !== $amount.'.00' || (string) $record->total !== $amount.'.00') {
            throw ValidationException::withMessages(['amount' => 'Nominal tagihan tidak sesuai snapshot pendaftaran.']);
        }
    }
}
