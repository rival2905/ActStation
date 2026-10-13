<?php

declare(strict_types=1);

namespace App\Modules\Payment\Models;

use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'payment_method_id',
        'invoice_number',
        'type',
        'payable_type',
        'payable_id',
        'amount',
        'fee',
        'total',
        'status',
        'paid_at',
        'expired_at',
        'gateway_ref',
        'meta',
    ];

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction): void {
            if (empty($transaction->invoice_number)) {
                $transaction->invoice_number = self::generateUniqueInvoiceNumber();
            }
        });
    }

    public static function generateUniqueInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';
        do {
            $candidate = $prefix.strtoupper(Str::random(6));
        } while (static::where('invoice_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
