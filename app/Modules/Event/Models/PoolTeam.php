<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoolTeam extends Model
{
    use Auditable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tournament_pool_id',
        'event_participant_id',
        'seed',
        'wins',
        'draws',
        'losses',
        'points_for',
        'points_against',
        'point_differential',
        'pool_points',
        'rank',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'wins' => 'integer',
            'draws' => 'integer',
            'losses' => 'integer',
            'points_for' => 'integer',
            'points_against' => 'integer',
            'point_differential' => 'integer',
            'pool_points' => 'integer',
            'rank' => 'integer',
        ];
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(TournamentPool::class, 'tournament_pool_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'event_participant_id');
    }

    public function getWonAttribute(): int
    {
        return (int) $this->wins;
    }

    public function getLostAttribute(): int
    {
        return (int) $this->losses;
    }

    public function getPointsAttribute(): int
    {
        return (int) $this->pool_points;
    }
}
