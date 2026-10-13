<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Resources;

use App\Modules\Event\Models\TournamentPool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TournamentPool
 */
class PoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournament_phase_id' => $this->tournament_phase_id,
            'name' => $this->name,
            'pool_order' => (int) $this->pool_order,
            'teams' => StandingsResource::collection($this->whenLoaded('teams')),
            'standings' => StandingsResource::collection($this->whenLoaded('teams')),
        ];
    }
}
