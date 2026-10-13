<?php

declare(strict_types=1);

namespace App\Modules\Event\Services\Tournament;

use App\Modules\Event\Models\BracketNode;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Models\TournamentPhase;
use Illuminate\Support\Facades\DB;

class PlacementGenerator
{
    /**
     * @param  array<int>  $positions
     */
    public function generate(TournamentPhase|int $phase, array $positions = [5, 7], TournamentPhase|int|null $knockoutPhase = null): TournamentPhase
    {
        return DB::transaction(function () use ($phase, $positions, $knockoutPhase): TournamentPhase {
            $phaseModel = $phase instanceof TournamentPhase ? $phase : TournamentPhase::findOrFail($phase);

            $knockoutPhaseModel = null;
            if ($knockoutPhase) {
                $knockoutPhaseModel = $knockoutPhase instanceof TournamentPhase ? $knockoutPhase : TournamentPhase::find($knockoutPhase);
            }
            if (! $knockoutPhaseModel) {
                $knockoutPhaseModel = TournamentPhase::query()
                    ->where('event_category_id', $phaseModel->event_category_id)
                    ->where('phase_type', 'knockout')
                    ->first();
            }

            // Clean up previous placement nodes and games
            $oldGameIds = BracketNode::query()
                ->where('tournament_phase_id', $phaseModel->id)
                ->pluck('game_id')
                ->filter();

            // Reset knockout loser links pointing to this phase
            if ($knockoutPhaseModel) {
                $thisPhaseNodeIds = BracketNode::query()->where('tournament_phase_id', $phaseModel->id)->pluck('id');
                BracketNode::query()
                    ->where('tournament_phase_id', $knockoutPhaseModel->id)
                    ->whereIn('loser_next_node_id', $thisPhaseNodeIds)
                    ->update([
                        'loser_next_node_id' => null,
                        'loser_next_slot' => null,
                    ]);
            }

            BracketNode::query()->where('tournament_phase_id', $phaseModel->id)->delete();
            if ($oldGameIds->isNotEmpty()) {
                Game::query()->whereIn('id', $oldGameIds)->delete();
            }
            Game::query()->where('tournament_phase_id', $phaseModel->id)->delete();

            $sortOrder = 1;

            // Placement for 5-8
            // Round 1: Semifinal 5-8 (2 matches)
            $semi1Game = Game::create([
                'event_id' => $phaseModel->event_id,
                'event_category_id' => $phaseModel->event_category_id,
                'tournament_phase_id' => $phaseModel->id,
                'match_label' => '5th-8th Semi 1',
                'round_label' => 'Placement Semifinals',
                'game_status' => 'scheduled',
                'is_bye' => false,
                'sort_order' => $sortOrder++,
            ]);

            $semi1Node = BracketNode::create([
                'event_id' => $phaseModel->event_id,
                'event_category_id' => $phaseModel->event_category_id,
                'tournament_phase_id' => $phaseModel->id,
                'phase' => 'placement',
                'round' => 1,
                'match_order' => 1,
                'node_type' => 'match',
                'game_id' => $semi1Game->id,
                'next_node_id' => null,
                'next_slot' => null,
                'loser_next_node_id' => null,
                'loser_next_slot' => null,
                'source_label' => '5-8_SEMI_1',
            ]);

            $semi2Game = Game::create([
                'event_id' => $phaseModel->event_id,
                'event_category_id' => $phaseModel->event_category_id,
                'tournament_phase_id' => $phaseModel->id,
                'match_label' => '5th-8th Semi 2',
                'round_label' => 'Placement Semifinals',
                'game_status' => 'scheduled',
                'is_bye' => false,
                'sort_order' => $sortOrder++,
            ]);

            $semi2Node = BracketNode::create([
                'event_id' => $phaseModel->event_id,
                'event_category_id' => $phaseModel->event_category_id,
                'tournament_phase_id' => $phaseModel->id,
                'phase' => 'placement',
                'round' => 1,
                'match_order' => 2,
                'node_type' => 'match',
                'game_id' => $semi2Game->id,
                'next_node_id' => null,
                'next_slot' => null,
                'loser_next_node_id' => null,
                'loser_next_slot' => null,
                'source_label' => '5-8_SEMI_2',
            ]);

            // Round 2: 5th Place Match
            $place5Game = Game::create([
                'event_id' => $phaseModel->event_id,
                'event_category_id' => $phaseModel->event_category_id,
                'tournament_phase_id' => $phaseModel->id,
                'match_label' => '5th Place Match',
                'round_label' => 'Placement Finals',
                'game_status' => 'scheduled',
                'is_bye' => false,
                'sort_order' => $sortOrder++,
            ]);

            $place5Node = BracketNode::create([
                'event_id' => $phaseModel->event_id,
                'event_category_id' => $phaseModel->event_category_id,
                'tournament_phase_id' => $phaseModel->id,
                'phase' => 'placement',
                'round' => 2,
                'match_order' => 1,
                'node_type' => 'match',
                'game_id' => $place5Game->id,
                'next_node_id' => null,
                'next_slot' => null,
                'loser_next_node_id' => null,
                'loser_next_slot' => null,
                'source_label' => '5TH_PLACE',
            ]);

            $semi1Node->update(['next_node_id' => $place5Node->id, 'next_slot' => 'a']);
            $semi2Node->update(['next_node_id' => $place5Node->id, 'next_slot' => 'b']);

            // Optional 7th Place Match
            if (in_array(7, $positions, true)) {
                $place7Game = Game::create([
                    'event_id' => $phaseModel->event_id,
                    'event_category_id' => $phaseModel->event_category_id,
                    'tournament_phase_id' => $phaseModel->id,
                    'match_label' => '7th Place Match',
                    'round_label' => 'Placement Finals',
                    'game_status' => 'scheduled',
                    'is_bye' => false,
                    'sort_order' => $sortOrder++,
                ]);

                $place7Node = BracketNode::create([
                    'event_id' => $phaseModel->event_id,
                    'event_category_id' => $phaseModel->event_category_id,
                    'tournament_phase_id' => $phaseModel->id,
                    'phase' => 'placement',
                    'round' => 2,
                    'match_order' => 2,
                    'node_type' => 'match',
                    'game_id' => $place7Game->id,
                    'next_node_id' => null,
                    'next_slot' => null,
                    'loser_next_node_id' => null,
                    'loser_next_slot' => null,
                    'source_label' => '7TH_PLACE',
                ]);

                $semi1Node->update(['loser_next_node_id' => $place7Node->id, 'loser_next_slot' => 'a']);
                $semi2Node->update(['loser_next_node_id' => $place7Node->id, 'loser_next_slot' => 'b']);
            }

            // Connect QF losers to placement semis
            if ($knockoutPhaseModel) {
                $qfNodes = BracketNode::query()
                    ->where('tournament_phase_id', $knockoutPhaseModel->id)
                    ->where('phase', 'knockout')
                    ->where('round', 1)
                    ->orderBy('match_order')
                    ->get();

                if ($qfNodes->count() >= 4) {
                    $qfNodes[0]->update(['loser_next_node_id' => $semi1Node->id, 'loser_next_slot' => 'a']);
                    $qfNodes[1]->update(['loser_next_node_id' => $semi1Node->id, 'loser_next_slot' => 'b']);
                    $qfNodes[2]->update(['loser_next_node_id' => $semi2Node->id, 'loser_next_slot' => 'a']);
                    $qfNodes[3]->update(['loser_next_node_id' => $semi2Node->id, 'loser_next_slot' => 'b']);
                }
            }

            $phaseModel->update([
                'status' => 'generated',
                'config' => array_merge($phaseModel->config ?? [], ['positions' => $positions]),
            ]);

            return $phaseModel->fresh(['bracketNodes.game']);
        });
    }
}
