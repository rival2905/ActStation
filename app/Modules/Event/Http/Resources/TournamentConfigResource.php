<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Resources;

use App\Modules\Event\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class TournamentConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => (string) $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'categories' => $this->categories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'gender' => $cat->gender,
                'min_roster' => $cat->min_roster,
                'max_roster' => $cat->max_roster,
                'max_teams' => $cat->max_teams,
            ]),
            'phases' => PhaseResource::collection($this->whenLoaded('tournamentPhases', $this->tournamentPhases)),
            'games' => GameResource::collection($this->whenLoaded('games')),
            'schedule_settings' => $this->scheduleSetting ? new ScheduleSettingResource($this->scheduleSetting) : null,
            'schedule_breaks' => ScheduleBreakResource::collection($this->scheduleBreaks),
            'courts' => CourtResource::collection($this->eventCourts),
            'participants' => $this->participants->map(fn ($p) => [
                'id' => $p->id,
                'event_category_id' => $p->event_category_id,
                'team_name' => $p->team_name,
                'status' => (string) $p->status,
            ]),
        ];
    }
}
