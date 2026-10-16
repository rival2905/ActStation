<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventScheduleSetting;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Services\EventCommitteeAccess;
use App\Modules\Event\Services\Tournament\ScheduleCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizerScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleCalculator $scheduleCalculator,
    ) {}

    public function index(Event $event): View
    {
        $this->authorizeOrganizer($event);

        $games = Game::query()
            ->where('event_id', $event->id)
            ->with(['category', 'teamA', 'teamB', 'ticketCategories'])
            ->orderBy('game_time')
            ->get();

        $setting = EventScheduleSetting::firstOrCreate(
            ['event_id' => $event->id],
            [
                'start_time' => '08:00',
                'end_time_daily' => '21:00',
                'game_duration' => 60,
                'break_between_games' => 15,
            ]
        );

        $workspace = request()->routeIs('admin.*') ? 'admin' : 'organizer';

        return view('panel.organizer.scheduling.index', compact('event', 'games', 'setting', 'workspace'));
    }

    public function autoRecalculate(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($event);

        $games = Game::query()->where('event_id', $event->id)->get();
        if ($games->isEmpty()) {
            return back()->with('error', 'Belum ada pertandingan untuk dijadwalkan. Generate fase turnamen terlebih dahulu.');
        }

        $this->scheduleCalculator->calculate($event);

        return back()->with('success', 'Algoritma ScheduleCalculator berhasil menghitung jadwal pertandingan secara berurutan.');
    }

    private function authorizeOrganizer(Event $event): void
    {
        if (! request()->routeIs('admin.*')) {
            app(EventCommitteeAccess::class)->current($event);
        }
    }
}
