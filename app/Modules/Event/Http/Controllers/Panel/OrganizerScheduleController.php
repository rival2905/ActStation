<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Contracts\EventLocationLookup;
use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\Panel\UpdateGameCourtRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventScheduleSetting;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Services\EventCommitteeAccess;
use App\Modules\Event\Services\Tournament\ScheduleCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        $setting = EventScheduleSetting::firstOrNew(
            ['event_id' => $event->id],
            [
                'start_time' => '08:00',
                'end_time_daily' => '21:00',
                'game_duration' => 60,
                'break_between_games' => 15,
            ]
        );

        $workspace = request()->routeIs('admin.*') ? 'admin' : 'organizer';

        $locations = app(EventLocationLookup::class)->options($event->id);

        return view('panel.organizer.scheduling.index', compact('event', 'games', 'setting', 'workspace', 'locations'));
    }

    public function updateCourt(UpdateGameCourtRequest $request, Event $event, Game $game): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        abort_unless((int) $game->event_id === (int) $event->id, 404);
        DB::transaction(function () use ($request, $event, $game): void {
            Event::query()->lockForUpdate()->findOrFail($event->id);
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);
            if ($game->is_bye || in_array($game->game_status, ['live', 'ongoing', 'finished', 'cancelled', 'bye'], true)) {
                throw ValidationException::withMessages(['venue_court_id' => 'Lapangan hanya dapat diubah sebelum pertandingan berjalan.']);
            }
            $courtId = $request->validated('venue_court_id');
            if ($courtId !== null && ! $event->eventCourts()->where('venue_court_id', $courtId)->exists()) {
                throw ValidationException::withMessages(['venue_court_id' => 'Pilih lapangan event yang masih terpasang.']);
            }
            $this->scheduleCalculator->assertCourtAvailable($game, $courtId === null ? null : (int) $courtId, $game->game_time);
            $game->update(['venue_court_id' => $courtId, 'venue_id' => $courtId === null ? null : DB::table('venue_courts')->where('id', $courtId)->value('venue_id')]);
        });

        return back()->with('success', 'Penempatan lapangan diperbarui. Tiket per laga mengikuti lokasi pertandingan terbaru.');
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
