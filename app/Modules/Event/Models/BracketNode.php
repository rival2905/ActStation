<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BracketNode extends Model
{
    use Auditable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'event_category_id',
        'tournament_phase_id',
        'phase',
        'round',
        'match_order',
        'node_type',
        'game_id',
        'next_node_id',
        'next_slot',
        'loser_next_node_id',
        'loser_next_slot',
        'source_label',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'match_order' => 'integer',
            'meta' => 'array',
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

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function nextNode(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_node_id');
    }

    public function loserNextNode(): BelongsTo
    {
        return $this->belongsTo(self::class, 'loser_next_node_id');
    }

    public function getTeamAAttribute(): ?EventParticipant
    {
        return $this->game?->teamA;
    }

    public function getTeamBAttribute(): ?EventParticipant
    {
        return $this->game?->teamB;
    }

    public function getScoreAAttribute(): ?int
    {
        return $this->game?->score_a;
    }

    public function getScoreBAttribute(): ?int
    {
        return $this->game?->score_b;
    }

    public function getNodeOrderAttribute(): int
    {
        return (int) $this->match_order;
    }

    public function getRoundNameAttribute(): string
    {
        return $this->game?->round_label ?? ('Round '.$this->round);
    }
}
