<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Resources;

use App\Modules\Event\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Game
 */
class GameResource extends JsonResource
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
            'venue_id' => $this->venue_id,
            'venue_court_id' => $this->venue_court_id,
            'team_a_id' => $this->team_a_id,
            'team_b_id' => $this->team_b_id,
            'team_a_label' => $this->team_a_label,
            'team_b_label' => $this->team_b_label,
            'team_a' => $this->teamA ? [
                'id' => $this->teamA->id,
                'team_name' => $this->teamA->team_name,
            ] : null,
            'team_b' => $this->teamB ? [
                'id' => $this->teamB->id,
                'team_name' => $this->teamB->team_name,
            ] : null,
            'game_time' => $this->game_time?->toIso8601String(),
            'game_status' => (string) $this->game_status,
            'score_a' => $this->score_a !== null ? (int) $this->score_a : null,
            'score_b' => $this->score_b !== null ? (int) $this->score_b : null,
            'match_label' => $this->match_label,
            'round_label' => $this->round_label,
            'is_bye' => (bool) $this->is_bye,
            'sort_order' => (int) $this->sort_order,
            'bracket_node_id' => $this->bracketNode?->id,
            'officials' => OfficialResource::collection($this->whenLoaded('officials')),
        ];
    }
}
