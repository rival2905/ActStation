<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Resources;

use App\Modules\Event\Models\PoolTeam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PoolTeam
 */
class StandingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournament_pool_id' => $this->tournament_pool_id,
            'event_participant_id' => $this->event_participant_id,
            'team_name' => $this->participant?->team_name ?? ('Team #'.$this->event_participant_id),
            'seed' => (int) $this->seed,
            'wins' => (int) $this->wins,
            'draws' => (int) $this->draws,
            'losses' => (int) $this->losses,
            'points_for' => (int) $this->points_for,
            'points_against' => (int) $this->points_against,
            'point_differential' => (int) $this->point_differential,
            'pool_points' => (int) $this->pool_points,
            'rank' => $this->rank !== null ? (int) $this->rank : null,
        ];
    }
}
