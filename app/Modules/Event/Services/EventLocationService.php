<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\EventLocationLookup;
use App\Modules\Event\Models\Event;
use Illuminate\Support\Facades\DB;

class EventLocationService implements EventLocationLookup
{
    public function options(int $eventId): array
    {
        $event = Event::findOrFail($eventId);
        $venueIds = app(EventVenueService::class)->venueIds($event);
        $venues = DB::table('venues')->whereIn('id', $venueIds)->orderBy('name')->get(['id', 'name'])->map(fn ($v) => (array) $v)->all();
        $courts = DB::table('event_courts as ec')->join('venue_courts as c', 'c.id', '=', 'ec.venue_court_id')
            ->join('venues as v', 'v.id', '=', 'c.venue_id')->where('ec.event_id', $eventId)->whereIn('v.id', $venueIds)
            ->orderBy('v.name')->orderBy('c.name')->get(['c.id', 'c.venue_id', 'c.name', 'v.name as venue_name'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'venue_id' => (int) $c->venue_id, 'name' => $c->venue_name.' — '.$c->name])->all();

        return compact('venues', 'courts');
    }

    public function game(int $eventId, int $gameId): ?array
    {
        $game = DB::table('games')->where('event_id', $eventId)->where('id', $gameId)->first(['venue_id', 'venue_court_id']);

        return $game ? (array) $game : null;
    }
}
