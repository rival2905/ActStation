<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Models;

use App\Contracts\DTOs\EventSummary;
use App\Contracts\EventLocationLookup;
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
        'location_scope',
        'venue_id',
        'venue_court_id',
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

    public function isOnSale(): bool
    {
        return $this->is_active && $this->sold_count < $this->quota
            && $this->locationIsAvailable()
            && ($this->sale_starts_at === null || $this->sale_starts_at->lte(now()))
            && ($this->sale_ends_at === null || $this->sale_ends_at->gt(now()))
            && ($this->valid_date === null || $this->valid_date->copy()->endOfDay()->gt(now()))
            && ($this->access_type !== 'per_game' || ($this->game !== null
                && (int) $this->game->event_id === (int) $this->event_id
                && $this->game->game_time?->gte(now()) && ! $this->game->is_bye
                && ! in_array($this->game->game_status, ['finished', 'cancelled'], true)));
    }

    private function locationIsAvailable(): bool
    {
        if (($this->location_scope ?? 'all') === 'all' && $this->access_type !== 'per_game') {
            return true;
        }
        $options = app(EventLocationLookup::class)->options((int) $this->event_id);
        if ($this->access_type === 'per_game') {
            return $this->game && in_array((int) $this->game->venue_court_id, array_column($options['courts'], 'id'), true);
        }

        return $this->location_scope === 'venue'
            ? in_array((int) $this->venue_id, array_map('intval', array_column($options['venues'], 'id')), true)
            : in_array((int) $this->venue_court_id, array_column($options['courts'], 'id'), true);
    }

    public function locationLabel(): string
    {
        $lookup = app(EventLocationLookup::class);
        $options = $lookup->options((int) $this->event_id);
        if ($this->access_type === 'per_game') {
            $game = $this->game_id ? $lookup->game((int) $this->event_id, (int) $this->game_id) : null;

            return collect($options['courts'])->firstWhere('id', (int) ($game['venue_court_id'] ?? 0))['name'] ?? 'Lokasi laga belum ditentukan';
        }
        if ($this->location_scope === 'court') {
            return collect($options['courts'])->firstWhere('id', (int) $this->venue_court_id)['name'] ?? 'Lapangan tidak tersedia';
        }
        if ($this->location_scope === 'venue') {
            return collect($options['venues'])->firstWhere('id', (int) $this->venue_id)['name'] ?? 'Venue tidak tersedia';
        }

        return 'Semua venue dan lapangan event';
    }

    public function allowsLocation(?int $venueId, ?int $courtId): bool
    {
        $lookup = app(EventLocationLookup::class);
        $options = $lookup->options((int) $this->event_id);
        if ($courtId !== null) {
            $court = collect($options['courts'])->firstWhere('id', $courtId);
            if (! $court || ($venueId !== null && $venueId !== $court['venue_id'])) {
                return false;
            }
            $venueId = $court['venue_id'];
        }
        if ($venueId !== null && ! in_array($venueId, array_map('intval', array_column($options['venues'], 'id')), true)) {
            return false;
        }
        if ($this->access_type === 'per_game') {
            $game = $this->game_id ? $lookup->game((int) $this->event_id, (int) $this->game_id) : null;

            return $courtId !== null && $game && (int) $game['venue_court_id'] === $courtId;
        }

        return match ($this->location_scope ?? 'all') {
            'court' => $courtId !== null && (int) $this->venue_court_id === $courtId,
            'venue' => $venueId !== null && (int) $this->venue_id === $venueId,
            default => true,
        };
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
