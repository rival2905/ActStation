<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Models;

use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use Auditable;

    protected $table = 'tickets';

    protected $fillable = [
        'ticket_category_id',
        'transaction_id',
        'user_id',
        'ticket_code',
        'qr_code',
        'holder_name',
        'holder_email',
        'seat_number',
        'status',
        'used_at',
    ];

    protected $hidden = [
        'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(TicketValidation::class, 'ticket_id');
    }
}
