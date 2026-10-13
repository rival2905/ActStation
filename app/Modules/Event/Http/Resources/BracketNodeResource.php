<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Resources;

use App\Modules\Event\Models\BracketNode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BracketNode
 */
class BracketNodeResource extends JsonResource
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
            'tournament_phase_id' => $this->tournament_phase_id,
            'phase' => (string) $this->phase,
            'round' => (int) $this->round,
            'match_order' => (int) $this->match_order,
            'node_type' => (string) $this->node_type,
            'game_id' => $this->game_id,
            'next_node_id' => $this->next_node_id,
            'next_slot' => $this->next_slot,
            'loser_next_node_id' => $this->loser_next_node_id,
            'loser_next_slot' => $this->loser_next_slot,
            'source_label' => $this->source_label,
            'meta' => $this->meta ?? [],
            'game' => $this->relationLoaded('game') && $this->game ? new GameResource($this->game) : null,
        ];
    }
}
