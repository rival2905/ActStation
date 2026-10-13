<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Resources;

use App\Modules\Event\Models\EventCourt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * @mixin EventCourt
 */
class CourtResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $courtName = null;
        $isIndoor = true;
        if ($this->venue_court_id) {
            $court = DB::table('venue_courts')->where('id', $this->venue_court_id)->first(['name', 'is_indoor']);
            $courtName = $court?->name;
            $isIndoor = (bool) ($court?->is_indoor ?? true);
        }

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'venue_court_id' => $this->venue_court_id,
            'name' => $courtName ?? ('Court #'.$this->venue_court_id),
            'court_name' => $courtName ?? ('Court #'.$this->venue_court_id),
            'is_indoor' => $isIndoor,
        ];
    }
}
