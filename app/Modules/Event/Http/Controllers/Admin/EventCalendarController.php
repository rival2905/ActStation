<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Event\Models\EventSchedule;
use App\Modules\Event\Models\Game;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventCalendarController extends Controller
{
    public function index(): View
    {
        $feedUrl = route('admin.events.calendar.feed');

        return view('admin.events.calendar', compact('feedUrl'));
    }

    public function feed(Request $request): JsonResponse
    {
        $start = $request->query('start');
        $end = $request->query('end');

        // 1. Pertandingan (Games)
        $gamesQuery = Game::query()
            ->with(['event', 'category'])
            ->whereNotNull('game_time');

        if ($start) {
            $gamesQuery->where('game_time', '>=', Carbon::parse((string) $start));
        }
        if ($end) {
            $gamesQuery->where('game_time', '<=', Carbon::parse((string) $end));
        }

        $games = $gamesQuery->get()->map(function (Game $game): array {
            $teamA = $game->team_a_label ?? 'TBD';
            $teamB = $game->team_b_label ?? 'TBD';
            $title = "{$teamA} vs {$teamB}";
            if ($game->match_label) {
                $title = "[{$game->match_label}] {$title}";
            }

            return [
                'id' => "game-{$game->id}",
                'title' => $title,
                'start' => $game->game_time?->toIso8601String(),
                'end' => $game->game_time?->copy()->addMinutes(60)->toIso8601String(),
                'url' => route('admin.events.show', $game->event_id),
                'backgroundColor' => '#2c7be5',
                'borderColor' => '#2c7be5',
                'extendedProps' => [
                    'type' => 'game',
                    'event_title' => $game->event?->title,
                    'category' => $game->category?->name,
                    'status' => $game->game_status,
                ],
            ];
        });

        // 2. Agenda & Jadwal Umum (EventSchedules)
        $schedulesQuery = EventSchedule::query()
            ->with('event')
            ->whereNotNull('scheduled_at');

        if ($start) {
            $schedulesQuery->where('scheduled_at', '>=', Carbon::parse((string) $start));
        }
        if ($end) {
            $schedulesQuery->where('scheduled_at', '<=', Carbon::parse((string) $end));
        }

        $schedules = $schedulesQuery->get()->map(function (EventSchedule $schedule): array {
            $endTime = $schedule->scheduled_at !== null && $schedule->duration_minutes !== null
                ? $schedule->scheduled_at->copy()->addMinutes($schedule->duration_minutes)->toIso8601String()
                : null;

            return [
                'id' => "schedule-{$schedule->id}",
                'title' => $schedule->title,
                'start' => $schedule->scheduled_at?->toIso8601String(),
                'end' => $endTime,
                'url' => route('admin.events.show', $schedule->event_id),
                'backgroundColor' => '#00d27a',
                'borderColor' => '#00d27a',
                'extendedProps' => [
                    'type' => 'schedule',
                    'event_title' => $schedule->event?->title,
                    'description' => $schedule->description,
                ],
            ];
        });

        return response()->json($games->concat($schedules)->values());
    }
}
