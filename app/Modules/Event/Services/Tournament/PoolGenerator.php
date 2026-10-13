<?php

declare(strict_types=1);

namespace App\Modules\Event\Services\Tournament;

use App\Modules\Event\Models\Game;
use App\Modules\Event\Models\PoolTeam;
use App\Modules\Event\Models\TournamentPhase;
use App\Modules\Event\Models\TournamentPool;
use Illuminate\Support\Facades\DB;

class PoolGenerator
{
    /**
     * @param  array<int>  $teamIds
     * @param  array<string, mixed>  $config
     */
    public function generate(TournamentPhase|int $phase, array $teamIds, array $config = []): TournamentPhase
    {
        return DB::transaction(function () use ($phase, $teamIds, $config): TournamentPhase {
            $phaseModel = $phase instanceof TournamentPhase ? $phase : TournamentPhase::findOrFail($phase);

            $mergedConfig = array_merge($phaseModel->config ?? [], $config);
            $poolCount = (int) ($mergedConfig['pool_count'] ?? 1);
            if ($poolCount < 1) {
                $poolCount = 1;
            }

            $roundRobinCount = (int) ($mergedConfig['round_robin_count'] ?? 1);
            if ($roundRobinCount < 1) {
                $roundRobinCount = 1;
            }

            // Cleanup previous generation for idempotency
            Game::query()->where('tournament_phase_id', $phaseModel->id)->delete();
            $oldPoolIds = TournamentPool::query()
                ->where('tournament_phase_id', $phaseModel->id)
                ->pluck('id');
            PoolTeam::query()->whereIn('tournament_pool_id', $oldPoolIds)->delete();
            TournamentPool::query()->where('tournament_phase_id', $phaseModel->id)->delete();

            // Create pools
            $pools = [];
            for ($i = 0; $i < $poolCount; $i++) {
                $poolName = $poolCount <= 26 ? 'Pool '.chr(65 + $i) : 'Pool '.($i + 1);
                $pools[] = TournamentPool::create([
                    'tournament_phase_id' => $phaseModel->id,
                    'name' => $poolName,
                    'pool_order' => $i + 1,
                ]);
            }

            // Distribute teams evenly into pools
            $poolTeamBuckets = array_fill(0, $poolCount, []);
            foreach (array_values($teamIds) as $idx => $teamId) {
                $poolIndex = $idx % $poolCount;
                $poolTeamBuckets[$poolIndex][] = $teamId;
            }

            $sortOrder = 1;

            foreach ($pools as $index => $pool) {
                $assignedTeams = $poolTeamBuckets[$index];

                foreach ($assignedTeams as $seed => $teamId) {
                    PoolTeam::create([
                        'tournament_pool_id' => $pool->id,
                        'event_participant_id' => $teamId,
                        'seed' => $seed + 1,
                        'wins' => 0,
                        'draws' => 0,
                        'losses' => 0,
                        'points_for' => 0,
                        'points_against' => 0,
                        'point_differential' => 0,
                        'pool_points' => 0,
                        'rank' => null,
                    ]);
                }

                $n = count($assignedTeams);
                if ($n < 2) {
                    continue;
                }

                // Circle method for round robin fixtures
                $teamsList = $assignedTeams;
                if ($n % 2 !== 0) {
                    $teamsList[] = null;
                }
                $m = count($teamsList);
                $rounds = $m - 1;

                for ($rr = 1; $rr <= $roundRobinCount; $rr++) {
                    $currentTeams = $teamsList;
                    for ($r = 1; $r <= $rounds; $r++) {
                        for ($k = 0; $k < $m / 2; $k++) {
                            $team1 = $currentTeams[$k];
                            $team2 = $currentTeams[$m - 1 - $k];

                            if ($team1 === null || $team2 === null || $team1 === $team2) {
                                continue;
                            }

                            // Swap home and away for alternate rounds or even rr passes
                            $home = ($rr % 2 === 1) ? $team1 : $team2;
                            $away = ($rr % 2 === 1) ? $team2 : $team1;

                            $roundNum = (($rr - 1) * $rounds) + $r;

                            Game::create([
                                'event_id' => $phaseModel->event_id,
                                'event_category_id' => $phaseModel->event_category_id,
                                'tournament_phase_id' => $phaseModel->id,
                                'team_a_id' => $home,
                                'team_b_id' => $away,
                                'match_label' => "{$pool->name}: Game {$sortOrder}",
                                'round_label' => "Round {$roundNum}",
                                'game_status' => 'scheduled',
                                'is_bye' => false,
                                'sort_order' => $sortOrder++,
                            ]);
                        }

                        // Rotate all except first element
                        $fixed = $currentTeams[0];
                        $rot = array_slice($currentTeams, 1);
                        $last = array_pop($rot);
                        array_unshift($rot, $last);
                        $currentTeams = array_merge([$fixed], $rot);
                    }
                }
            }

            $mergedConfig['team_count'] = count($teamIds);
            $mergedConfig['teams_per_pool'] = (int) ceil(count($teamIds) / $poolCount);

            $phaseModel->update([
                'status' => 'generated',
                'config' => $mergedConfig,
            ]);

            return $phaseModel->fresh(['pools.teams', 'games']);
        });
    }
}
