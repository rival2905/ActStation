<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\Game;
use Illuminate\View\View;

class PublicResultController extends Controller
{
    public function index(string $slug): View
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->with(['categories'])
            ->firstOrFail();

        $finishedGames = Game::query()
            ->where('event_id', $event->id)
            ->where('game_status', 'finished')
            ->with(['category', 'teamA', 'teamB'])
            ->latest('game_time')
            ->get();

        $liveGames = Game::query()
            ->where('event_id', $event->id)
            ->where('game_status', 'live')
            ->with(['category', 'teamA', 'teamB'])
            ->get();

        return view('public.events.results', compact('event', 'finishedGames', 'liveGames'));
    }
}
