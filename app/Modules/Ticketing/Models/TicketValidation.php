<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Models;

use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketValidation extends Model
{
    use Auditable;

    protected $table = 'ticket_validations';

    protected $fillable = [
        'ticket_id',
        'validated_by',
        'validated_at',
        'gate',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
