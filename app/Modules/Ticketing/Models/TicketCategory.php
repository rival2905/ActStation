<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Models;

use App\Contracts\DTOs\EventSummary;
use App\Contracts\EventLookup;
use App\Models\Concerns\Auditable;
use App\Modules\Event\Models\Game;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    use Auditable;

    protected $table = 'ticket_categories';

    protected $fillable = [
        'event_id',
        'name',
        'price',
        'quota',
        'sold_count',
        'description',
        'sale_starts_at',
        'sale_ends_at',
        'is_active',
        'access_type',
        'seating_tier',
        'seating_zone',
        'valid_date',
        'game_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quota' => 'integer',
            'sold_count' => 'integer',
            'is_active' => 'boolean',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'valid_date' => 'date',
            'game_id' => 'integer',
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticket_category_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_id');
    }

    public function getEventSummary(): ?EventSummary
    {
        /** @var EventLookup $lookup */
        $lookup = app(EventLookup::class);

        return $lookup->find($this->event_id);
    }
}
