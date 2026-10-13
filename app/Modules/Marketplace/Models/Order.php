<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Models;

use App\Contracts\Payable;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model implements Payable
{
    use Auditable, HasFactory;

    protected $table = 'orders';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'transaction_id',
        'order_number',
        'status',
        'total',
        'shipping_address',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function getPayableType(): string
    {
        return 'order';
    }

    public function getPayableId(): int
    {
        return (int) $this->id;
    }

    public function getAmount(): string
    {
        return (string) ($this->total ?? '0.00');
    }

    public function getDescription(): string
    {
        return "Order #{$this->order_number}";
    }
}
