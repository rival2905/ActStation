<?php

declare(strict_types=1);

namespace App\Modules\Event\Services\Tournament;

use App\Modules\Event\Models\BracketNode;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Models\TournamentPhase;
use Illuminate\Support\Facades\DB;

class BracketGenerator
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function generate(TournamentPhase|int $phase, array $config = []): TournamentPhase
    {
        return DB::transaction(function () use ($phase, $config): TournamentPhase {
            $phaseModel = $phase instanceof TournamentPhase ? $phase : TournamentPhase::findOrFail($phase);

            $mergedConfig = array_merge($phaseModel->config ?? [], $config);
            $rawSize = (int) ($mergedConfig['bracket_size'] ?? 8);
            if ($rawSize < 2) {
                $rawSize = 2;
            }

            // Ensure bracket size is a power of 2
            $bracketSize = 1;
            while ($bracketSize < $rawSize) {
                $bracketSize *= 2;
            }

            $thirdPlaceMatch = (bool) ($mergedConfig['third_place_match'] ?? false);
            $totalRounds = (int) log($bracketSize, 2);

            // Clean up old nodes and games for idempotency
            $oldNodeGameIds = BracketNode::query()
                ->where('tournament_phase_id', $phaseModel->id)
                ->pluck('game_id')
                ->filter();

            BracketNode::query()->where('tournament_phase_id', $phaseModel->id)->delete();
            if ($oldNodeGameIds->isNotEmpty()) {
                Game::query()->whereIn('id', $oldNodeGameIds)->delete();
            }
            Game::query()->where('tournament_phase_id', $phaseModel->id)->delete();
            /** @var array<int, array<int, BracketNode>> $nodesByRound */
            $nodesByRound = [];
            $sortOrder = 1;

            $poolPhase = TournamentPhase::where('event_id', $phaseModel->event_id)
                ->where('event_category_id', $phaseModel->event_category_id)
                ->where('phase_type', 'pool')
                ->first();

            // Generate nodes from Final down to Round 1
            for ($r = $totalRounds; $r >= 1; $r--) {
                $matchCount = (int) ($bracketSize / (2 ** $r));
                $roundLabel = $this->getRoundLabel($r, $totalRounds);

                for ($m = 1; $m <= $matchCount; $m++) {
                    $matchLabel = $matchCount === 1 ? $roundLabel : "{$roundLabel} {$m}";

                    $teamALabel = null;
                    $teamBLabel = null;

                    if ($r === 1) {
                        if ($poolPhase && $matchCount === 2) {
                            $teamALabel = $m === 1 ? 'Juara Pool A' : 'Juara Pool B';
                            $teamBLabel = $m === 1 ? 'Runner-up Pool B' : 'Runner-up Pool A';
                        } elseif ($poolPhase && $matchCount === 4) {
                            $pairLabels = [
                                1 => ['Juara Pool A', 'Runner-up Pool B'],
                                2 => ['Juara Pool C', 'Runner-up Pool D'],
                                3 => ['Juara Pool B', 'Runner-up Pool A'],
                                4 => ['Juara Pool D', 'Runner-up Pool C'],
                            ];
                            $teamALabel = $pairLabels[$m][0] ?? "Seed {$m}";
                            $teamBLabel = $pairLabels[$m][1] ?? 'Seed '.($bracketSize - $m + 1);
                        } else {
                            $seedPairs = $this->getSeedPairs($bracketSize);
                            $pair = $seedPairs[$m - 1] ?? [$m, $bracketSize - $m + 1];
                            $teamALabel = "Seed {$pair[0]}";
                            $teamBLabel = "Seed {$pair[1]}";
                        }
                    } else {
                        $prevDiff = $totalRounds - ($r - 1);
                        $prevShort = match ($prevDiff) {
                            1 => 'SF',
                            2 => 'QF',
                            3 => 'R16',
                            default => 'R'.($r - 1),
                        };
                        $feederA = ($m - 1) * 2 + 1;
                        $feederB = ($m - 1) * 2 + 2;
                        $teamALabel = "Win {$prevShort}{$feederA}";
                        $teamBLabel = "Win {$prevShort}{$feederB}";
                    }

                    $game = Game::create([
                        'event_id' => $phaseModel->event_id,
                        'event_category_id' => $phaseModel->event_category_id,
                        'tournament_phase_id' => $phaseModel->id,
                        'team_a_label' => $teamALabel,
                        'team_b_label' => $teamBLabel,
                        'match_label' => $matchLabel,
                        'round_label' => $roundLabel,
                        'game_status' => 'scheduled',
                        'is_bye' => false,
                        'sort_order' => $sortOrder++,
                    ]);
                    $nextNodeId = null;
                    $nextSlot = null;

                    if ($r < $totalRounds) {
                        $parentOrder = (int) (intdiv($m - 1, 2) + 1);
                        $parentNode = $nodesByRound[$r + 1][$parentOrder] ?? null;
                        if ($parentNode) {
                            $nextNodeId = $parentNode->id;
                            $nextSlot = ($m % 2 === 1) ? 'a' : 'b';
                        }
                    }

                    $node = BracketNode::create([
                        'event_id' => $phaseModel->event_id,
                        'event_category_id' => $phaseModel->event_category_id,
                        'tournament_phase_id' => $phaseModel->id,
                        'phase' => 'knockout',
                        'round' => $r,
                        'match_order' => $m,
                        'node_type' => 'match',
                        'game_id' => $game->id,
                        'next_node_id' => $nextNodeId,
                        'next_slot' => $nextSlot,
                        'loser_next_node_id' => null,
                        'loser_next_slot' => null,
                        'source_label' => "R{$r}M{$m}",
                    ]);

                    $nodesByRound[$r][$m] = $node;
                }
            }

            // Create third place match if required
            if ($thirdPlaceMatch && $totalRounds >= 2) {
                $tpGame = Game::create([
                    'event_id' => $phaseModel->event_id,
                    'event_category_id' => $phaseModel->event_category_id,
                    'tournament_phase_id' => $phaseModel->id,
                    'team_a_label' => 'Kalah SF1',
                    'team_b_label' => 'Kalah SF2',
                    'match_label' => '3rd Place Match',
                    'round_label' => 'Third Place',
                    'game_status' => 'scheduled',
                    'is_bye' => false,
                    'sort_order' => $sortOrder++,
                ]);
                $tpNode = BracketNode::create([
                    'event_id' => $phaseModel->event_id,
                    'event_category_id' => $phaseModel->event_category_id,
                    'tournament_phase_id' => $phaseModel->id,
                    'phase' => 'third_place',
                    'round' => $totalRounds,
                    'match_order' => 2,
                    'node_type' => 'match',
                    'game_id' => $tpGame->id,
                    'next_node_id' => null,
                    'next_slot' => null,
                    'loser_next_node_id' => null,
                    'loser_next_slot' => null,
                    'source_label' => '3RD_PLACE',
                ]);

                // Link Semifinal losers to Third Place match
                $sfRound = $totalRounds - 1;
                if (isset($nodesByRound[$sfRound][1])) {
                    $nodesByRound[$sfRound][1]->update([
                        'loser_next_node_id' => $tpNode->id,
                        'loser_next_slot' => 'a',
                    ]);
                }
                if (isset($nodesByRound[$sfRound][2])) {
                    $nodesByRound[$sfRound][2]->update([
                        'loser_next_node_id' => $tpNode->id,
                        'loser_next_slot' => 'b',
                    ]);
                }
            }

            $mergedConfig['bracket_size'] = $bracketSize;
            $phaseModel->update([
                'status' => 'generated',
                'config' => $mergedConfig,
            ]);

            return $phaseModel->fresh(['bracketNodes.game']);
        });
    }

    private function getRoundLabel(int $round, int $totalRounds): string
    {
        $diff = $totalRounds - $round;

        return match ($diff) {
            0 => 'Final',
            1 => 'Semifinals',
            2 => 'Quarterfinals',
            3 => 'Round of 16',
            4 => 'Round of 32',
            default => "Round {$round}",
        };
    }

    private function getSeedPairs(int $size): array
    {
        if ($size === 4) {
            return [[1, 4], [2, 3]];
        }
        if ($size === 8) {
            return [[1, 8], [4, 5], [2, 7], [3, 6]];
        }
        if ($size === 16) {
            return [
                [1, 16], [8, 9], [4, 13], [5, 12],
                [2, 15], [7, 10], [3, 14], [6, 11],
            ];
        }

        $pairs = [];
        $matches = (int) ($size / 2);
        for ($i = 1; $i <= $matches; $i++) {
            $pairs[] = [$i, $size - $i + 1];
        }

        return $pairs;
    }
}
