<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\DTOs\EventSummary;
use App\Contracts\EventLookup;
use App\Modules\Event\Models\Event;

class EventLookupService implements EventLookup
{
    public function find(int $id): ?EventSummary
    {
        $event = Event::find($id);

        if (! $event) {
            return null;
        }

        return new EventSummary(
            id: (int) $event->id,
            title: (string) $event->title,
            slug: (string) $event->slug,
            status: (string) $event->status,
            starts_at: $event->starts_at,
            venue_id: $event->venue_id !== null ? (int) $event->venue_id : null,
        );
    }
}
