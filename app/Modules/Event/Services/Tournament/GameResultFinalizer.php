<?php

declare(strict_types=1);

namespace App\Modules\Event\Services\Tournament;

use App\Modules\Event\Models\BracketNode;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Models\PoolTeam;
use App\Modules\Event\Models\TournamentPhase;
use App\Modules\Event\Models\TournamentPool;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class GameResultFinalizer
{
    public function finalize(Game|int $game, int $scoreA, int $scoreB): Game
    {
        return DB::transaction(function () use ($game, $scoreA, $scoreB): Game {
            $gameId = $game instanceof Game ? $game->id : $game;

            /** @var Game $gameModel */
            $gameModel = Game::query()
                ->with(['phase'])
                ->lockForUpdate()
                ->findOrFail($gameId);

            /** @var BracketNode|null $bracketNode */
            $bracketNode = BracketNode::query()
                ->where('game_id', $gameModel->id)
                ->lockForUpdate()
                ->first();

            $nextNode = null;
            $nextGame = null;
            if ($bracketNode?->next_node_id) {
                $nextNode = BracketNode::query()
                    ->where('id', $bracketNode->next_node_id)
                    ->lockForUpdate()
                    ->first();
                if ($nextNode?->game_id) {
                    $nextGame = Game::query()
                        ->where('id', $nextNode->game_id)
                        ->lockForUpdate()
                        ->first();
                }
            }

            $loserNextNode = null;
            $loserNextGame = null;
            if ($bracketNode?->loser_next_node_id) {
                $loserNextNode = BracketNode::query()
                    ->where('id', $bracketNode->loser_next_node_id)
                    ->lockForUpdate()
                    ->first();
                if ($loserNextNode?->game_id) {
                    $loserNextGame = Game::query()
                        ->where('id', $loserNextNode->game_id)
                        ->lockForUpdate()
                        ->first();
                }
            }

            $isKnockout = $bracketNode !== null
                || ($gameModel->phase?->phase_type === 'knockout')
                || ($gameModel->phase?->phase_type === 'placement');

            if ($isKnockout) {
                if ($scoreA === $scoreB) {
                    throw new InvalidArgumentException('Skor seri tidak diperbolehkan pada babak knockout.');
                }

                if ($nextGame && $nextGame->game_status === 'finished') {
                    throw new LogicException('Edit skor ditolak: game di ronde berikutnya sudah selesai.');
                }

                if ($loserNextGame && $loserNextGame->game_status === 'finished') {
                    throw new LogicException('Edit skor ditolak: game perebutan posisi berikutnya sudah selesai.');
                }
            }

            $gameModel->update([
                'score_a' => $scoreA,
                'score_b' => $scoreB,
                'game_status' => 'finished',
            ]);

            // Handle Knockout advancement
            if ($bracketNode) {
                $winnerId = ($scoreA > $scoreB) ? $gameModel->team_a_id : $gameModel->team_b_id;
                $loserId = ($scoreA > $scoreB) ? $gameModel->team_b_id : $gameModel->team_a_id;

                if ($nextGame && $winnerId) {
                    $slotField = $bracketNode->next_slot === 'b' ? 'team_b_id' : 'team_a_id';
                    $nextGame->update([$slotField => $winnerId]);
                }

                if ($loserNextGame && $loserId) {
                    $slotField = $bracketNode->loser_next_slot === 'b' ? 'team_b_id' : 'team_a_id';
                    $loserNextGame->update([$slotField => $loserId]);
                }
            }

            // Handle Pool Standings recalculation
            if ($gameModel->tournament_phase_id && ($gameModel->phase?->phase_type === 'pool' || ! $isKnockout)) {
                $this->recalculatePoolStandings($gameModel->tournament_phase_id);
            }

            return $gameModel->fresh(['phase', 'bracketNode']);
        });
    }

    public function recalculatePoolStandings(TournamentPhase|int $phase): void
    {
        $phaseModel = $phase instanceof TournamentPhase ? $phase : TournamentPhase::findOrFail($phase);

        $config = $phaseModel->config ?? [];
        $pointWin = (int) ($config['point_win'] ?? 2);
        $pointDraw = (int) ($config['point_draw'] ?? 1);
        $pointLose = (int) ($config['point_lose'] ?? 0);

        $pools = TournamentPool::query()
            ->with(['teams'])
            ->where('tournament_phase_id', $phaseModel->id)
            ->get();

        foreach ($pools as $pool) {
            $poolTeams = $pool->teams;
            if ($poolTeams->isEmpty()) {
                continue;
            }

            $teamIds = $poolTeams->pluck('event_participant_id')->all();

            $finishedGames = Game::query()
                ->where('tournament_phase_id', $phaseModel->id)
                ->where('game_status', 'finished')
                ->whereNotNull('score_a')
                ->whereNotNull('score_b')
                ->whereIn('team_a_id', $teamIds)
                ->whereIn('team_b_id', $teamIds)
                ->get();

            /** @var array<int, array<string, mixed>> $stats */
            $stats = [];
            foreach ($poolTeams as $pt) {
                $tId = (int) $pt->event_participant_id;
                $stats[$tId] = [
                    'pool_team' => $pt,
                    'wins' => 0,
                    'draws' => 0,
                    'losses' => 0,
                    'points_for' => 0,
                    'points_against' => 0,
                    'pool_points' => 0,
                    'seed' => (int) ($pt->seed ?? 999),
                ];
            }

            foreach ($finishedGames as $fg) {
                $tA = (int) $fg->team_a_id;
                $tB = (int) $fg->team_b_id;
                $sA = (int) $fg->score_a;
                $sB = (int) $fg->score_b;

                if (! isset($stats[$tA]) || ! isset($stats[$tB])) {
                    continue;
                }

                $stats[$tA]['points_for'] += $sA;
                $stats[$tA]['points_against'] += $sB;
                $stats[$tB]['points_for'] += $sB;
                $stats[$tB]['points_against'] += $sA;

                if ($sA > $sB) {
                    $stats[$tA]['wins']++;
                    $stats[$tA]['pool_points'] += $pointWin;
                    $stats[$tB]['losses']++;
                    $stats[$tB]['pool_points'] += $pointLose;
                } elseif ($sB > $sA) {
                    $stats[$tB]['wins']++;
                    $stats[$tB]['pool_points'] += $pointWin;
                    $stats[$tA]['losses']++;
                    $stats[$tA]['pool_points'] += $pointLose;
                } else {
                    $stats[$tA]['draws']++;
                    $stats[$tA]['pool_points'] += $pointDraw;
                    $stats[$tB]['draws']++;
                    $stats[$tB]['pool_points'] += $pointDraw;
                }
            }

            // Precompute H2H metrics for each team against other teams sharing the exact same pool_points
            foreach ($stats as $tId => &$tData) {
                $tiedIds = array_keys(array_filter($stats, fn (array $s): bool => $s['pool_points'] === $tData['pool_points']));
                $h2hPoints = 0;
                $h2hDiff = 0;

                if (count($tiedIds) > 1) {
                    $otherTiedIds = array_diff($tiedIds, [$tId]);
                    $h2hGames = $finishedGames->filter(function (Game $g) use ($tId, $otherTiedIds): bool {
                        $teamA = (int) $g->team_a_id;
                        $teamB = (int) $g->team_b_id;

                        return ($teamA === $tId && in_array($teamB, $otherTiedIds, true))
                            || ($teamB === $tId && in_array($teamA, $otherTiedIds, true));
                    });

                    foreach ($h2hGames as $hg) {
                        $sA = (int) $hg->score_a;
                        $sB = (int) $hg->score_b;
                        $teamScore = ((int) $hg->team_a_id === $tId) ? $sA : $sB;
                        $oppScore = ((int) $hg->team_a_id === $tId) ? $sB : $sA;

                        if ($teamScore > $oppScore) {
                            $h2hPoints += $pointWin;
                        } elseif ($oppScore > $teamScore) {
                            $h2hPoints += $pointLose;
                        } else {
                            $h2hPoints += $pointDraw;
                        }

                        $h2hDiff += ($teamScore - $oppScore);
                    }
                }

                $tData['h2h_points'] = $h2hPoints;
                $tData['h2h_diff'] = $h2hDiff;
            }
            unset($tData);

            // Sort teams based on tiebreaker hierarchy:
            // 1. pool_points (desc)
            // 2. head-to-head point in tied group (desc)
            // 3. head-to-head selisih skor in tied group (desc)
            // 4. selisih skor total (point_differential) (desc)
            // 5. total poin memasukkan (points_for) (desc)
            // 6. seed (asc)
            $sortedTeamIds = array_keys($stats);
            usort($sortedTeamIds, function (int $idA, int $idB) use ($stats): int {
                // 1. pool_points
                if ($stats[$idA]['pool_points'] !== $stats[$idB]['pool_points']) {
                    return $stats[$idB]['pool_points'] <=> $stats[$idA]['pool_points'];
                }

                // 2. head-to-head points
                if ($stats[$idA]['h2h_points'] !== $stats[$idB]['h2h_points']) {
                    return $stats[$idB]['h2h_points'] <=> $stats[$idA]['h2h_points'];
                }

                // 3. head-to-head score differential
                if ($stats[$idA]['h2h_diff'] !== $stats[$idB]['h2h_diff']) {
                    return $stats[$idB]['h2h_diff'] <=> $stats[$idA]['h2h_diff'];
                }

                // 4. Overall score differential
                $diffA = $stats[$idA]['points_for'] - $stats[$idA]['points_against'];
                $diffB = $stats[$idB]['points_for'] - $stats[$idB]['points_against'];
                if ($diffA !== $diffB) {
                    return $diffB <=> $diffA;
                }

                // 5. Total points scored
                if ($stats[$idA]['points_for'] !== $stats[$idB]['points_for']) {
                    return $stats[$idB]['points_for'] <=> $stats[$idA]['points_for'];
                }

                // 6. Seed (lower seed number is better)
                return $stats[$idA]['seed'] <=> $stats[$idB]['seed'];
            });

            // Update database records
            foreach ($sortedTeamIds as $rankIndex => $tId) {
                $st = $stats[$tId];
                /** @var PoolTeam $pt */
                $pt = $st['pool_team'];
                $pt->update([
                    'wins' => $st['wins'],
                    'draws' => $st['draws'],
                    'losses' => $st['losses'],
                    'points_for' => $st['points_for'],
                    'points_against' => $st['points_against'],
                    'point_differential' => $st['points_for'] - $st['points_against'],
                    'pool_points' => $st['pool_points'],
                    'rank' => $rankIndex + 1,
                ]);
            }
        }
    }
}
