<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Resources;

use App\Modules\Event\Models\EventScheduleSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventScheduleSetting
 */
class ScheduleSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'start_time' => (string) $this->start_time,
            'end_time_daily' => (string) $this->end_time_daily,
            'break_between_games' => (int) $this->break_between_games,
            'durations' => $this->durations ?? [],
        ];
    }
}
