<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Resources;

use App\Modules\Event\Models\EventScheduleBreak;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventScheduleBreak
 */
class ScheduleBreakResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'date' => $this->date?->format('Y-m-d') ?? (string) $this->date,
            'start_time' => (string) $this->start_time,
            'end_time' => (string) $this->end_time,
            'label' => $this->label,
        ];
    }
}
