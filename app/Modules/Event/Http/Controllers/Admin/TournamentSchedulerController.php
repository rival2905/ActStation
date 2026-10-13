<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Admin;

use App\Contracts\OfficialDirectory;
use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\Admin\Scheduler\AssignTeamsRequest;
use App\Modules\Event\Http\Requests\Admin\Scheduler\FinalizeGameRequest;
use App\Modules\Event\Http\Requests\Admin\Scheduler\GeneratePhaseRequest;
use App\Modules\Event\Http\Requests\Admin\Scheduler\PlotOfficialsRequest;
use App\Modules\Event\Http\Requests\Admin\Scheduler\StorePhaseRequest;
use App\Modules\Event\Http\Requests\Admin\Scheduler\UpdateGameRequest;
use App\Modules\Event\Http\Requests\Admin\Scheduler\UpdatePhaseRequest;
use App\Modules\Event\Http\Resources\BracketNodeResource;
use App\Modules\Event\Http\Resources\GameResource;
use App\Modules\Event\Http\Resources\OfficialResource;
use App\Modules\Event\Http\Resources\PhaseResource;
use App\Modules\Event\Http\Resources\PoolResource;
use App\Modules\Event\Http\Resources\StandingsResource;
use App\Modules\Event\Http\Resources\TournamentConfigResource;
use App\Modules\Event\Models\BracketNode;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Models\PoolTeam;
use App\Modules\Event\Models\TournamentPhase;
use App\Modules\Event\Models\TournamentPool;
use App\Modules\Event\Services\Exports\StandingsCsvExport;
use App\Modules\Event\Services\Tournament\BracketGenerator;
use App\Modules\Event\Services\Tournament\BracketSeeder;
use App\Modules\Event\Services\Tournament\GameResultFinalizer;
use App\Modules\Event\Services\Tournament\PlacementGenerator;
use App\Modules\Event\Services\Tournament\PoolGenerator;
use App\Modules\Event\Services\Tournament\ScheduleCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TournamentSchedulerController extends Controller
{
    public function mount(Event $event): View
    {
        return view('admin.events.scheduler', [
            'event' => $event,
            'apiBase' => url(sprintf('%s/events/%d/scheduler/api', request()->routeIs('panel.organizer.*') ? 'panel/organizer' : 'admin', $event->id)),
        ]);
    }

    public function config(Event $event): TournamentConfigResource
    {
        $event->load([
            'categories',
            'tournamentPhases.pools.teams.participant',
            'tournamentPhases.bracketNodes.game.teamA',
            'tournamentPhases.bracketNodes.game.teamB',
            'tournamentPhases.games.teamA',
            'tournamentPhases.games.teamB',
            'games.teamA',
            'games.teamB',
            'scheduleSetting',
            'scheduleBreaks',
            'eventCourts',
            'participants',
        ]);

        return new TournamentConfigResource($event);
    }

    public function storePhase(Event $event, StorePhaseRequest $request): PhaseResource
    {
        $validated = $request->validated();
        $categoryId = (int) $validated['event_category_id'];
        $phaseType = (string) $validated['phase_type'];

        if (! isset($validated['phase_order'])) {
            $existing = $event->tournamentPhases()
                ->where('event_category_id', $categoryId)
                ->where('phase_type', $phaseType)
                ->first();

            if ($existing) {
                $existing->update([
                    'name' => $validated['name'],
                    'config' => $validated['config'] ?? $existing->config,
                    'advancement_rules' => $validated['advancement_rules'] ?? $existing->advancement_rules,
                    'status' => 'draft',
                ]);

                return new PhaseResource($existing);
            }

            $validated['phase_order'] = match ($phaseType) {
                'pool' => 1,
                'knockout' => 2,
                'placement' => 3,
                default => (int) ($event->tournamentPhases()
                    ->where('event_category_id', $categoryId)
                    ->max('phase_order') ?? 0) + 1,
            };
        }

        /** @var TournamentPhase $phase */
        $phase = $event->tournamentPhases()->updateOrCreate(
            [
                'event_category_id' => $categoryId,
                'phase_type' => $phaseType,
                'phase_order' => $validated['phase_order'],
            ],
            $validated
        );

        return new PhaseResource($phase);
    }

    public function updatePhase(Event $event, TournamentPhase $phase, UpdatePhaseRequest $request): PhaseResource
    {
        abort_unless((int) $phase->event_id === (int) $event->id, 404);

        $phase->update($request->validated());

        return new PhaseResource($phase);
    }

    public function destroyPhase(Event $event, TournamentPhase $phase): JsonResponse
    {
        abort_unless((int) $phase->event_id === (int) $event->id, 404);

        $phase->delete();

        return response()->json(['message' => 'Phase deleted successfully']);
    }

    public function generate(
        Event $event,
        TournamentPhase $phase,
        GeneratePhaseRequest $request,
        PoolGenerator $poolGen,
        BracketGenerator $bracketGen,
        PlacementGenerator $placementGen
    ): PhaseResource {
        abort_unless((int) $phase->event_id === (int) $event->id, 404);

        $type = $phase->phase_type;

        if ($type === 'pool') {
            /** @var list<int> $teamIds */
            $teamIds = $request->input('team_ids') ?? $event->participants()
                ->where('event_category_id', $phase->event_category_id)
                ->pluck('id')
                ->all();

            $poolGen->generate($phase, $teamIds, $request->input('config', []));
        } elseif ($type === 'knockout') {
            $bracketGen->generate($phase, $request->input('config', []));
        } elseif ($type === 'placement') {
            $placementGen->generate(
                $phase,
                $request->input('positions', [5, 7]),
                $request->input('knockout_phase_id')
            );
        }

        return new PhaseResource($phase->fresh(['pools.teams.participant', 'bracketNodes.game', 'games']));
    }

    public function assignTeams(Event $event, AssignTeamsRequest $request, BracketSeeder $seeder): PhaseResource
    {
        $phaseId = (int) $request->input('phase_id');
        /** @var TournamentPhase $phase */
        $phase = $event->tournamentPhases()->findOrFail($phaseId);

        $standingsOrTeams = $request->input('standings') ?? $request->input('teams', []);
        $crossPool = $request->boolean('cross_pool', true);

        $seeder->seed($phase, $standingsOrTeams, $crossPool);

        return new PhaseResource($phase->fresh(['bracketNodes.game', 'games']));
    }

    public function finalizeGame(
        Event $event,
        Game $game,
        FinalizeGameRequest $request,
        GameResultFinalizer $finalizer
    ): GameResource {
        abort_unless((int) $game->event_id === (int) $event->id, 404);

        $finalized = $finalizer->finalize(
            $game,
            (int) $request->input('score_a'),
            (int) $request->input('score_b')
        );

        return new GameResource($finalized->fresh(['officials.user', 'bracketNode', 'teamA', 'teamB']));
    }

    public function resetAll(Event $event): JsonResponse
    {
        DB::transaction(function () use ($event): void {
            $event->games()->delete();
            BracketNode::query()->where('event_id', $event->id)->delete();
            $poolIds = TournamentPool::query()
                ->whereIn('tournament_phase_id', $event->tournamentPhases()->pluck('id'))
                ->pluck('id');
            PoolTeam::query()->whereIn('tournament_pool_id', $poolIds)->delete();
            TournamentPool::query()->whereIn('id', $poolIds)->delete();
            $event->tournamentPhases()->update(['status' => 'draft']);
        });

        return response()->json(['message' => 'All tournament data reset successfully']);
    }

    public function resetPhase(Event $event, TournamentPhase $phase): JsonResponse
    {
        abort_unless((int) $phase->event_id === (int) $event->id, 404);

        DB::transaction(function () use ($phase): void {
            BracketNode::query()->where('tournament_phase_id', $phase->id)->delete();
            $poolIds = $phase->pools()->pluck('id');
            PoolTeam::query()->whereIn('tournament_pool_id', $poolIds)->delete();
            $phase->pools()->delete();
            $phase->update(['status' => 'draft']);
        });

        return response()->json(['message' => 'Phase reset successfully']);
    }

    public function pools(Event $event, Request $request): AnonymousResourceCollection
    {
        $query = TournamentPool::query()
            ->with(['teams.participant'])
            ->whereIn('tournament_phase_id', $event->tournamentPhases()->pluck('id'));

        if ($request->filled('phase_id')) {
            $query->where('tournament_phase_id', (int) $request->input('phase_id'));
        }

        return PoolResource::collection($query->get());
    }

    public function standings(Event $event, Request $request): AnonymousResourceCollection
    {
        $poolIds = TournamentPool::query()
            ->whereIn('tournament_phase_id', $event->tournamentPhases()->pluck('id'))
            ->pluck('id');

        $query = PoolTeam::query()
            ->with(['participant', 'pool'])
            ->whereIn('tournament_pool_id', $poolIds);

        if ($request->filled('phase_id')) {
            $phasePoolIds = TournamentPool::query()
                ->where('tournament_phase_id', (int) $request->input('phase_id'))
                ->pluck('id');
            $query->whereIn('tournament_pool_id', $phasePoolIds);
        }

        return StandingsResource::collection($query->orderBy('tournament_pool_id')->orderBy('rank')->get());
    }

    public function bracketNodes(Event $event, Request $request): AnonymousResourceCollection
    {
        $query = BracketNode::query()
            ->with(['game.teamA', 'game.teamB'])
            ->where('event_id', $event->id);

        if ($request->filled('phase_id')) {
            $query->where('tournament_phase_id', (int) $request->input('phase_id'));
        }

        return BracketNodeResource::collection($query->orderBy('round')->orderBy('match_order')->get());
    }

    public function games(Event $event, Request $request): AnonymousResourceCollection
    {
        $query = $event->games()->with(['teamA', 'teamB', 'bracketNode', 'officials.user']);

        if ($request->filled('phase_id')) {
            $query->where('tournament_phase_id', (int) $request->input('phase_id'));
        }

        return GameResource::collection($query->orderBy('sort_order')->get());
    }

    public function updateGame(Event $event, Game $game, UpdateGameRequest $request): GameResource
    {
        abort_unless((int) $game->event_id === (int) $event->id, 404);

        $game->update($request->validated());

        return new GameResource($game->fresh(['teamA', 'teamB', 'bracketNode', 'officials.user']));
    }

    public function plotOfficials(Event $event, Game $game, PlotOfficialsRequest $request): GameResource
    {
        abort_unless((int) $game->event_id === (int) $event->id, 404);

        /** @var list<array{user_id: int, duty: string}> $officials */
        $officials = $request->input('officials', []);
        DB::transaction(function () use ($game, $officials): void {
            $game->officials()->delete();
            foreach ($officials as $official) {
                $game->officials()->create([
                    'user_id' => $official['user_id'],
                    'duty' => $official['duty'],
                ]);
            }
        });

        return new GameResource($game->fresh(['teamA', 'teamB', 'bracketNode', 'officials.user']));
    }

    public function officials(Event $event, Request $request, OfficialDirectory $directory): AnonymousResourceCollection
    {
        $duty = (string) $request->input('duty', 'referee');
        $officials = $directory->search($duty);

        return OfficialResource::collection($officials);
    }

    public function recalculateSchedule(
        Event $event,
        Request $request,
        ScheduleCalculator $calculator
    ): AnonymousResourceCollection {
        $minTeamRest = $request->filled('min_team_rest') ? (int) $request->input('min_team_rest') : null;
        $calculator->calculate($event, minTeamRest: $minTeamRest);

        return GameResource::collection($event->games()->with(['teamA', 'teamB', 'officials.user'])->get());
    }

    public function exportStandingsCsv(Event $event, Request $request, StandingsCsvExport $export): StreamedResponse
    {
        $phaseQuery = $event->tournamentPhases()->where('phase_type', 'pool');
        if ($request->filled('phase_id')) {
            $phase = $phaseQuery->where('id', (int) $request->input('phase_id'))->firstOrFail();
        } else {
            $phase = $phaseQuery->firstOrFail();
        }

        return $export->download($phase);
    }
}
