<?php

declare(strict_types=1);

namespace App\Modules\Event\Services\Tournament;

use App\Modules\Event\Models\BracketNode;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Models\PoolTeam;
use App\Modules\Event\Models\TournamentPhase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BracketSeeder
{
    /**
     * @param  TournamentPhase|int|iterable<BracketNode>  $phaseOrNodes
     * @param  array<mixed>|Collection<int, mixed>  $standingsOrTeams
     * @return Collection<int, BracketNode>
     */
    public function seed(TournamentPhase|int|iterable $phaseOrNodes, array|Collection $standingsOrTeams, bool $crossPool = true): Collection
    {
        return DB::transaction(function () use ($phaseOrNodes, $standingsOrTeams, $crossPool): Collection {
            $nodes = $this->resolveRoundOneNodes($phaseOrNodes);

            if ($nodes->isEmpty()) {
                return collect();
            }

            $bracketSize = $nodes->count() * 2;
            $pairings = [];

            $isPoolStructure = $this->isPoolStructure($standingsOrTeams);

            if ($crossPool && $isPoolStructure) {
                $pairings = $this->buildCrossPoolPairings($standingsOrTeams, $nodes->count());
            } else {
                $teamList = $this->normalizeTeamsList($standingsOrTeams);
                $pairings = $this->buildStandardPairings($teamList, $bracketSize);
            }

            foreach ($nodes as $index => $node) {
                $pair = $pairings[$index] ?? [null, null];
                $teamA = $pair[0];
                $teamB = $pair[1];

                $game = $node->game ?? Game::find($node->game_id);
                if (! $game) {
                    continue;
                }

                $isBye = ($teamA !== null && $teamB === null) || ($teamA === null && $teamB !== null);
                $byeTeamId = $teamA ?? $teamB;

                if ($isBye) {
                    $game->update([
                        'team_a_id' => $byeTeamId,
                        'team_b_id' => null,
                        'is_bye' => true,
                        'game_status' => 'bye',
                    ]);

                    $node->update(['node_type' => 'bye']);

                    // Automatically advance the bye team to the next round node
                    if ($node->next_node_id && $byeTeamId) {
                        $nextNode = BracketNode::with('game')->find($node->next_node_id);
                        if ($nextNode && $nextNode->game) {
                            $slotField = $node->next_slot === 'b' ? 'team_b_id' : 'team_a_id';
                            $nextNode->game->update([$slotField => $byeTeamId]);
                        }
                    }
                } else {
                    $game->update([
                        'team_a_id' => $teamA,
                        'team_b_id' => $teamB,
                        'is_bye' => false,
                        'game_status' => 'scheduled',
                    ]);

                    $node->update(['node_type' => 'match']);
                }
            }

            return $nodes->fresh(['game']);
        });
    }

    /**
     * @param  TournamentPhase|int|iterable<BracketNode>  $phaseOrNodes
     * @return Collection<int, BracketNode>
     */
    private function resolveRoundOneNodes(TournamentPhase|int|iterable $phaseOrNodes): Collection
    {
        if ($phaseOrNodes instanceof TournamentPhase || is_int($phaseOrNodes)) {
            $phaseId = $phaseOrNodes instanceof TournamentPhase ? $phaseOrNodes->id : $phaseOrNodes;

            return BracketNode::query()
                ->with('game')
                ->where('tournament_phase_id', $phaseId)
                ->where('phase', 'knockout')
                ->where('round', 1)
                ->orderBy('match_order')
                ->get();
        }

        if ($phaseOrNodes instanceof Collection) {
            $filtered = $phaseOrNodes->filter(fn ($n) => ($n->round ?? 1) === 1 && ($n->phase ?? 'knockout') === 'knockout');

            return $filtered->isNotEmpty() ? $filtered->sortBy('match_order')->values() : $phaseOrNodes->values();
        }

        return collect($phaseOrNodes);
    }

    /**
     * @param  array<mixed>|Collection<int, mixed>  $data
     */
    private function isPoolStructure(array|Collection $data): bool
    {
        $items = $data instanceof Collection ? $data->all() : $data;
        if (empty($items)) {
            return false;
        }

        $first = reset($items);
        if ($first instanceof PoolTeam) {
            return true;
        }

        if (is_array($first) && (isset($first['pool_id']) || isset($first['pool']) || isset($first['tournament_pool_id']))) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<mixed>|Collection<int, mixed>  $data
     * @return array<int, array{0: ?int, 1: ?int}>
     */
    private function buildCrossPoolPairings(array|Collection $data, int $matchCount): array
    {
        $pools = [];

        foreach ($data as $item) {
            if ($item instanceof PoolTeam) {
                $poolId = (int) $item->tournament_pool_id;
                $teamId = (int) $item->event_participant_id;
                $rank = (int) ($item->rank ?? $item->seed ?? 1);
            } elseif (is_array($item)) {
                $poolId = (int) ($item['tournament_pool_id'] ?? $item['pool_id'] ?? 1);
                $teamId = (int) ($item['event_participant_id'] ?? $item['team_id'] ?? $item['id'] ?? 0);
                $rank = (int) ($item['rank'] ?? $item['seed'] ?? 1);
            } else {
                continue;
            }

            $pools[$poolId][$rank] = $teamId;
        }

        $poolKeys = array_keys($pools);
        sort($poolKeys);
        $poolCount = count($poolKeys);

        $pairings = [];

        if ($poolCount === 2) {
            // Pool 0 vs Pool 1
            $p0 = $poolKeys[0];
            $p1 = $poolKeys[1];
            // M1: Winner P0 vs Runner-up P1
            $pairings[] = [$pools[$p0][1] ?? null, $pools[$p1][2] ?? null];
            // M2: Winner P1 vs Runner-up P0
            $pairings[] = [$pools[$p1][1] ?? null, $pools[$p0][2] ?? null];
        } elseif ($poolCount === 4) {
            // Pools A, B, C, D (keys 0, 1, 2, 3)
            $pA = $poolKeys[0];
            $pB = $poolKeys[1];
            $pC = $poolKeys[2];
            $pD = $poolKeys[3];

            // Match 1: 1A vs 2B
            $pairings[] = [$pools[$pA][1] ?? null, $pools[$pB][2] ?? null];
            // Match 2: 1C vs 2D
            $pairings[] = [$pools[$pC][1] ?? null, $pools[$pD][2] ?? null];
            // Match 3: 1B vs 2A
            $pairings[] = [$pools[$pB][1] ?? null, $pools[$pA][2] ?? null];
            // Match 4: 1D vs 2C
            $pairings[] = [$pools[$pD][1] ?? null, $pools[$pC][2] ?? null];
        } else {
            // Generic cross-pool rotation: Winner i vs Runner-up (i + 1) % P
            for ($i = 0; $i < $matchCount; $i++) {
                $winnerPool = $poolKeys[$i % $poolCount] ?? null;
                $runnerPool = $poolKeys[($i + 1) % $poolCount] ?? null;

                $winner = $winnerPool ? ($pools[$winnerPool][1] ?? null) : null;
                $runner = $runnerPool ? ($pools[$runnerPool][2] ?? null) : null;

                $pairings[] = [$winner, $runner];
            }
        }

        return $pairings;
    }

    /**
     * @param  array<mixed>|Collection<int, mixed>  $data
     * @return array<int, ?int>
     */
    private function normalizeTeamsList(array|Collection $data): array
    {
        $result = [];
        foreach ($data as $item) {
            if ($item instanceof PoolTeam) {
                $result[] = (int) $item->event_participant_id;
            } elseif (is_array($item)) {
                $result[] = (int) ($item['event_participant_id'] ?? $item['team_id'] ?? $item['id'] ?? 0);
            } elseif (is_numeric($item)) {
                $result[] = (int) $item;
            } elseif ($item === null) {
                $result[] = null;
            }
        }

        return $result;
    }

    /**
     * Standard seeding bracket slot assignment.
     * Generates: M1: [1, 8], M2: [4, 5], M3: [2, 7], M4: [3, 6] for size 8.
     *
     * @param  array<int, ?int>  $teamList
     * @return array<int, array{0: ?int, 1: ?int}>
     */
    private function buildStandardPairings(array $teamList, int $bracketSize): array
    {
        $slots = [1, 2];
        while (count($slots) < $bracketSize) {
            $nextSize = count($slots) * 2;
            $newSlots = [];
            foreach ($slots as $seed) {
                $newSlots[] = $seed;
                $newSlots[] = $nextSize + 1 - $seed;
            }
            $slots = $newSlots;
        }

        $pairings = [];
        $totalMatches = (int) ($bracketSize / 2);

        for ($m = 0; $m < $totalMatches; $m++) {
            $seedA = $slots[$m * 2];
            $seedB = $slots[($m * 2) + 1];

            $teamA = $teamList[$seedA - 1] ?? null;
            $teamB = $teamList[$seedB - 1] ?? null;

            $pairings[] = [$teamA, $teamB];
        }

        return $pairings;
    }
}
