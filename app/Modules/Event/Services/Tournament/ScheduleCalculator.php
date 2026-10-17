<?php

declare(strict_types=1);

namespace App\Modules\Event\Services\Tournament;

use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCourt;
use App\Modules\Event\Models\EventScheduleBreak;
use App\Modules\Event\Models\EventScheduleSetting;
use App\Modules\Event\Models\Game;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ScheduleCalculator
{
    /**
     * @param  Collection<int, Game>|array<Game>|null  $games
     * @param  Collection<int, EventCourt>|array<EventCourt>|null  $courts
     * @param  Collection<int, EventScheduleBreak>|array<EventScheduleBreak>|null  $breaks
     * @return Collection<int, Game>
     */
    public function calculate(
        Event|int $event,
        Collection|array|null $games = null,
        Collection|array|null $courts = null,
        ?EventScheduleSetting $settings = null,
        Collection|array|null $breaks = null,
        ?int $minTeamRest = null
    ): Collection {
        return DB::transaction(function () use ($event, $games, $courts, $settings, $breaks, $minTeamRest): Collection {
            $eventModel = Event::query()->lockForUpdate()->findOrFail($event instanceof Event ? $event->id : $event);

            $settingModel = $settings ?? EventScheduleSetting::query()->where('event_id', $eventModel->id)->first();
            $dailyStartTime = $settingModel?->start_time ?? '08:00';
            $dailyEndTime = $settingModel?->end_time_daily ?? '21:00';
            $breakBetweenGames = (int) ($settingModel?->break_between_games ?? 0);
            $durations = $settingModel?->durations;

            $teamRestMinutes = $minTeamRest
                ?? (is_array($durations) && isset($durations['min_team_rest']) ? (int) $durations['min_team_rest'] : $breakBetweenGames);

            $courtCollection = $courts !== null ? collect($courts) : EventCourt::query()->where('event_id', $eventModel->id)->get();
            if ($courtCollection->isEmpty()) {
                throw new InvalidArgumentException('Tidak ada lapangan (courts) yang terdaftar untuk event ini.');
            }

            $breakCollection = $breaks !== null ? collect($breaks) : EventScheduleBreak::query()->where('event_id', $eventModel->id)->get();

            $gameCollection = $games !== null
                ? collect($games)
                : Game::query()
                    ->where('event_id', $eventModel->id)
                    ->where('is_bye', false)
                    ->where('game_status', '!=', 'finished')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

            // Filter out finished games and byes
            $gamesToSchedule = $gameCollection
                ->filter(fn ($g) => $g->game_status !== 'finished' && ! $g->is_bye)
                ->values();

            if ($gamesToSchedule->isEmpty()) {
                return collect();
            }

            $initialDate = $eventModel->starts_at
                ? Carbon::parse($eventModel->starts_at)->toDateString()
                : Carbon::today()->toDateString();

            /** @var array<int, Carbon> $courtClocks */
            $courtClocks = [];
            foreach ($courtCollection->values() as $idx => $c) {
                $courtClocks[$idx] = Carbon::parse("{$initialDate} {$dailyStartTime}");
            }

            /** @var array<int, Carbon> $teamNextAvailable */
            $teamNextAvailable = [];

            /** @var Collection<int, Game> $scheduledGames */
            $scheduledGames = collect();

            foreach ($gamesToSchedule as $game) {
                $duration = $this->getGameDuration($game, $durations);

                $teamA = $game->team_a_id ? (int) $game->team_a_id : null;
                $teamB = $game->team_b_id ? (int) $game->team_b_id : null;

                $teamEarliest = null;
                if ($teamA && isset($teamNextAvailable[$teamA])) {
                    $teamEarliest = $teamNextAvailable[$teamA];
                }
                if ($teamB && isset($teamNextAvailable[$teamB])) {
                    if (! $teamEarliest || $teamNextAvailable[$teamB]->gt($teamEarliest)) {
                        $teamEarliest = $teamNextAvailable[$teamB];
                    }
                }

                $bestCourtIdx = null;
                $bestSlotStart = null;

                foreach ($courtCollection->values() as $courtIdx => $courtObj) {
                    $candidate = $courtClocks[$courtIdx]->copy();
                    if ($teamEarliest && $teamEarliest->gt($candidate)) {
                        $candidate = $teamEarliest->copy();
                    }

                    $slot = $this->findNextAvailableSlot(
                        $candidate,
                        $duration,
                        $dailyStartTime,
                        $dailyEndTime,
                        $breakCollection
                    );

                    if ($bestSlotStart === null || $slot->lt($bestSlotStart)) {
                        $bestSlotStart = $slot;
                        $bestCourtIdx = $courtIdx;
                    }
                }

                $chosenCourt = $courtCollection->values()->get($bestCourtIdx);
                $venueCourtId = $chosenCourt->venue_court_id ?? $chosenCourt->id;

                $gameEnd = $bestSlotStart->copy()->addMinutes($duration);

                $game->update([
                    'game_time' => $bestSlotStart,
                    'venue_court_id' => $venueCourtId,
                    'venue_id' => DB::table('venue_courts')->where('id', $venueCourtId)->value('venue_id'),
                ]);

                // Advance court clock
                $courtClocks[$bestCourtIdx] = $gameEnd->copy()->addMinutes($breakBetweenGames);

                // Update team rest availability
                $teamFreeAt = $gameEnd->copy()->addMinutes($teamRestMinutes);
                if ($teamA) {
                    $teamNextAvailable[$teamA] = $teamFreeAt;
                }
                if ($teamB) {
                    $teamNextAvailable[$teamB] = $teamFreeAt;
                }

                $scheduledGames->push($game->fresh());
            }

            return $scheduledGames;
        });
    }

    public function assertCourtAvailable(Game $game, ?int $courtId, mixed $time): void
    {
        if ($courtId === null || $time === null) {
            return;
        }
        $start = Carbon::parse($time);
        $setting = EventScheduleSetting::where('event_id', $game->event_id)->first();
        $duration = max(1, $this->getGameDuration($game, $setting?->durations));
        $gap = max(0, (int) $setting?->break_between_games);
        $end = $start->copy()->addMinutes($duration + $gap);
        $others = Game::where('event_id', $game->event_id)->where('id', '!=', $game->id)
            ->where('venue_court_id', $courtId)->whereNotNull('game_time')->where('is_bye', false)
            ->whereNotIn('game_status', ['cancelled', 'bye'])->get();
        foreach ($others as $other) {
            $otherEnd = $other->game_time->copy()->addMinutes(max(1, $this->getGameDuration($other, $setting?->durations)) + $gap);
            if ($start->lt($otherEnd) && $end->gt($other->game_time)) {
                throw ValidationException::withMessages(['venue_court_id' => 'Lapangan dipakai laga lain pada rentang waktu ini. Ubah waktu atau pilih lapangan lain.']);
            }
        }
    }

    private function getGameDuration(Game $game, mixed $durations): int
    {
        if (is_numeric($durations)) {
            return (int) $durations;
        }

        if (is_array($durations)) {
            $phaseType = $game->phase?->phase_type;
            if ($phaseType && isset($durations[$phaseType])) {
                return (int) $durations[$phaseType];
            }

            return (int) ($durations['default'] ?? $durations['game'] ?? 60);
        }

        return 60;
    }

    private function findNextAvailableSlot(
        Carbon $candidateTime,
        int $duration,
        string $dailyStartTime,
        string $dailyEndTime,
        Collection $breaks
    ): Carbon {
        $time = $candidateTime->copy();
        $iterations = 0;

        while ($iterations++ < 1000) {
            $dateStr = $time->toDateString();
            $dayStart = Carbon::parse("{$dateStr} {$dailyStartTime}");
            $dayEnd = Carbon::parse("{$dateStr} {$dailyEndTime}");

            if ($time->lt($dayStart)) {
                $time = $dayStart->copy();
            }

            $endTime = $time->copy()->addMinutes($duration);

            if ($endTime->gt($dayEnd)) {
                $time = Carbon::parse("{$dateStr} {$dailyStartTime}")->addDay();

                continue;
            }

            $breakCollision = false;
            foreach ($breaks as $brk) {
                $breakDate = is_string($brk->date) ? substr($brk->date, 0, 10) : Carbon::parse($brk->date)->toDateString();
                if ($breakDate !== $dateStr) {
                    continue;
                }

                $bStart = Carbon::parse("{$dateStr} {$brk->start_time}");
                $bEnd = Carbon::parse("{$dateStr} {$brk->end_time}");

                if ($time->lt($bEnd) && $endTime->gt($bStart)) {
                    $time = $bEnd->copy();
                    $breakCollision = true;
                    break;
                }
            }

            if ($breakCollision) {
                continue;
            }

            return $time;
        }

        return $time;
    }
}
