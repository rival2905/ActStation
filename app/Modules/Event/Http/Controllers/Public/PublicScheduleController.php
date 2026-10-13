<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\Game;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicScheduleController extends Controller
{
    public function index(Request $request, string $slug): View
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->with(['categories'])
            ->firstOrFail();

        $games = Game::query()
            ->where('event_id', $event->id)
            ->with(['category', 'teamA', 'teamB'])
            ->orderBy('game_time')
            ->get();

        return view('public.events.schedule', compact('event', 'games'));
    }
}
