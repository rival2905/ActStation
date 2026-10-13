<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Models\Concerns\Auditable;
use App\Modules\Ticketing\Models\TicketCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Game extends Model
{
    use Auditable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'event_category_id',
        'tournament_phase_id',
        'venue_id',
        'venue_court_id',
        'team_a_id',
        'team_b_id',
        'team_a_label',
        'team_b_label',
        'game_time',
        'game_status',
        'score_a',
        'score_b',
        'match_label',
        'round_label',
        'is_bye',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'game_time' => 'datetime',
            'score_a' => 'integer',
            'score_b' => 'integer',
            'is_bye' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(TournamentPhase::class, 'tournament_phase_id');
    }

    public function teamA(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'team_a_id');
    }

    public function teamB(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'team_b_id');
    }

    public function bracketNode(): HasOne
    {
        return $this->hasOne(BracketNode::class);
    }

    public function officials(): HasMany
    {
        return $this->hasMany(GameOfficial::class);
    }

    public function ticketCategories(): HasMany
    {
        return $this->hasMany(TicketCategory::class, 'game_id');
    }
}
