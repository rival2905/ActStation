<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Resources;

use App\Modules\Event\Models\TournamentPhase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TournamentPhase
 */
class PhaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_category_id' => $this->event_category_id,
            'name' => $this->name,
            'phase_type' => (string) $this->phase_type,
            'phase_order' => (int) $this->phase_order,
            'status' => (string) $this->status,
            'config' => $this->config ?? [],
            'advancement_rules' => $this->advancement_rules ?? [],
            'pools' => PoolResource::collection($this->whenLoaded('pools')),
            'nodes' => BracketNodeResource::collection($this->whenLoaded('bracketNodes')),
            'bracket_nodes' => BracketNodeResource::collection($this->whenLoaded('bracketNodes')),
            'games' => GameResource::collection($this->whenLoaded('games')),
        ];
    }
}
