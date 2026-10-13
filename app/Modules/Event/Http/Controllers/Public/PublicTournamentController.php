<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Event\Models\Event;
use Illuminate\View\View;

class PublicTournamentController extends Controller
{
    public function index(string $slug): View
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->with([
                'categories',
                'tournamentPhases.category',
                'tournamentPhases.pools.poolTeams.participant',
                'tournamentPhases.bracketNodes.game.teamA',
                'tournamentPhases.bracketNodes.game.teamB',
            ])
            ->firstOrFail();

        return view('public.events.tournament', compact('event'));
    }
}
