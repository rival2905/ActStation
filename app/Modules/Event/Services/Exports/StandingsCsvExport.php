<?php

declare(strict_types=1);

namespace App\Modules\Event\Services\Exports;

use App\Modules\Event\Models\TournamentPhase;
use App\Modules\Event\Models\TournamentPool;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StandingsCsvExport
{
    // ponytail: CSV stream ceiling reached; upgrade to PhpSpreadsheet xlsx export when styled multi-sheet export is required.
    public function download(TournamentPhase $phase): StreamedResponse
    {
        $phase->loadMissing(['pools.teams.participant']);

        $filename = sprintf('standings-phase-%d.csv', $phase->id);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($phase): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row with ';' delimiter
            fputcsv($handle, [
                'Pool',
                'Rank',
                'Team',
                'Pld',
                'W',
                'D',
                'L',
                'PF',
                'PA',
                'DIFF',
                'PTS',
            ], ';');

            foreach ($phase->pools->sortBy('pool_order') as $pool) {
                /** @var TournamentPool $pool */
                $teams = $pool->teams->sortBy('rank');
                foreach ($teams as $team) {
                    fputcsv($handle, [
                        $pool->name,
                        $team->rank ?? '-',
                        $team->participant?->team_name ?? ('Team #'.$team->event_participant_id),
                        ($team->wins + $team->draws + $team->losses),
                        $team->wins,
                        $team->draws,
                        $team->losses,
                        $team->points_for,
                        $team->points_against,
                        $team->point_differential,
                        $team->pool_points,
                    ], ';');
                }
            }

            fclose($handle);
        }, $filename, $headers);
    }
}
