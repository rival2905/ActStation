<?php

declare(strict_types=1);

namespace App\Modules\Scoreboard\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BasketballEngine
{
    public function initial(string $sessionId, string $home, string $away, int $nowMs): array
    {
        $team = fn (string $name) => ['name' => $name, 'community_id' => null, 'score' => 0, 'team_fouls' => 0, 'timeouts_used' => 0];
        $clock = fn (int $ms) => ['remaining_ms' => $ms, 'running' => false, 'deadline_ms' => null];

        return ['schema_version' => 'basketball.v1', 'sport' => 'basketball', 'session_id' => $sessionId,
            'revision' => 0, 'authority_epoch' => 1, 'authority' => 'virtual_server',
            'ruleset_id' => 'basketball-5x5-training', 'ruleset_version' => 1,
            'ruleset' => ['regulation_periods' => 4, 'period_ms' => 600000, 'overtime_ms' => 300000, 'shot_presets_ms' => [24000, 14000, 12000], 'timeouts_max' => 5, 'training_only' => true],
            'home' => $team($home), 'away' => $team($away), 'period' => ['type' => 'regulation', 'number' => 1],
            'period_history' => [],
            'period_active' => [
                'period' => ['type' => 'regulation', 'number' => 1],
                'home' => ['score' => 0, 'team_fouls' => 0],
                'away' => ['score' => 0, 'team_fouls' => 0],
            ],
            'unallocated_score' => ['home' => 0, 'away' => 0],
            'possession' => 'none', 'game_clock' => $clock(600000), 'shot_clock' => $clock(24000) + ['display_enabled' => true],
            'timeout_clock' => $clock(0), 'signal' => null];
    }

    public function materialize(array $s, int $nowMs): array
    {
        $s = $this->withLedger($s);
        // Expire chronologically: freezing shot at game expiry must not swallow an earlier shot expiry.
        $due = [];
        foreach (['game_clock', 'shot_clock', 'timeout_clock'] as $key) {
            if ($s[$key]['running'] && $s[$key]['deadline_ms'] <= $nowMs) {
                $due[$key] = $s[$key]['deadline_ms'];
            }
        }
        asort($due);
        foreach ($due as $key => $deadline) {
            if (! $s[$key]['running']) {
                continue;
            }
            $s[$key] = array_replace($s[$key], ['remaining_ms' => 0, 'running' => false, 'deadline_ms' => null]);
            if ($key === 'game_clock') {
                $this->stop($s, 'shot_clock', $deadline);
            }
            if ($key !== 'timeout_clock') {
                $s['signal'] = $this->signal($key === 'game_clock' ? 'game_expired' : 'shot_expired', $deadline);
            }
            $s['revision']++;
        }
        foreach (['game_clock', 'shot_clock', 'timeout_clock'] as $key) {
            if ($s[$key]['running']) {
                $s[$key]['remaining_ms'] = max(0, $s[$key]['deadline_ms'] - $nowMs);
            }
        }

        return $s;
    }

    public function apply(array $s, string $type, array $p, int $nowMs): array
    {
        $s = $this->materialize($s, $nowMs);
        $before = $s;
        $allowed = match ($type) {
            'score.adjust', 'team_fouls.adjust', 'timeouts_used.adjust' => ['team', 'delta'],
            'game_clock.set', 'shot_clock.set' => ['remaining_ms'],
            'shot_clock.adjust' => ['delta_ms'],
            'shot_clock.reset', 'timeout_clock.start', 'period.duration.set' => ['duration_ms'],
            'shot_clock.display.set' => ['enabled'], 'possession.set' => ['team'],
            'period.set' => ['type', 'number'], 'team_fouls.reset', 'session.reset' => ['confirmed', 'reason'],
            'period.correct' => ['type', 'number', 'home_score', 'away_score', 'home_fouls', 'away_fouls', 'reason', 'note'],
            'game_clock.reset', 'game_clock.start', 'game_clock.stop', 'shot_clock.start', 'shot_clock.stop', 'timeout_clock.stop', 'horn.trigger' => [],
            default => $this->invalid('Command tidak dikenal.'),
        };
        if (array_diff(array_keys($p), $allowed) || array_diff($allowed, array_keys($p))) {
            $this->invalid('Payload command tidak cocok.');
        }
        if (in_array($type, ['team_fouls.reset', 'session.reset'], true)) {
            if (($p['confirmed'] ?? null) !== true || ! is_string($p['reason']) || trim($p['reason']) === '' || mb_strlen($p['reason']) > 200) {
                $this->invalid('Konfirmasi dan alasan reset wajib.');
            }
        }
        if (in_array($type, ['game_clock.reset', 'period.duration.set', 'game_clock.set', 'period.set', 'team_fouls.reset', 'session.reset', 'period.correct'], true)) {
            if ($s['game_clock']['running'] || $s['shot_clock']['running'] || $s['timeout_clock']['running']) {
                $this->invalid('Hentikan semua clocks dahulu.');
            }
        }
        switch ($type) {
            case 'score.adjust': case 'team_fouls.adjust': case 'timeouts_used.adjust':
                $team = $p['team'];
                if (! in_array($team, ['home', 'away'], true)) {
                    $this->invalid('Tim tidak sah.');
                }
                $field = explode('.', $type)[0];
                $delta = $p['delta'];
                if (! is_int($delta) || ! in_array($delta, $field === 'score' ? [-1, 1, 2, 3] : [-1, 1], true)) {
                    $this->invalid('Delta tidak sah.');
                }
                $value = $s[$team][$field] + $delta;
                $max = match ($field) {
                    'score' => 999, 'team_fouls' => 99, default => 5
                };
                if ($value < 0 || $value > $max) {
                    $this->invalid('Nilai di luar batas preset latihan.');
                }
                if (in_array($field, ['score', 'team_fouls'], true)) {
                    $active = $s['period_active'][$team][$field] + $delta;
                    if ($active < 0) {
                        $this->invalid('Koreksi melebihi catatan periode aktif. Buka periode terkait dahulu.');
                    }
                    $s['period_active'][$team][$field] = $active;
                }
                $s[$team][$field] = $value;
                break;
            case 'game_clock.start':
                if ($s['timeout_clock']['running']) {
                    $this->invalid('Timeout masih berjalan.');
                }
                $this->start($s, 'game_clock', $nowMs);
                $s['period_active']['clock_started'] = true;
                break;
            case 'game_clock.stop':
                $this->stop($s, 'game_clock', $nowMs);
                $this->stop($s, 'shot_clock', $nowMs);
                break;
            case 'shot_clock.start':
                if (! $s['game_clock']['running'] || $s['timeout_clock']['running']) {
                    $this->invalid('Shot clock perlu game berjalan tanpa timeout.');
                }
                $this->start($s, 'shot_clock', $nowMs);
                break;
            case 'shot_clock.stop': case 'timeout_clock.stop':
                $this->stop($s, explode('.', $type)[0], $nowMs);
                break;
            case 'period.duration.set':
                $s['ruleset'][$s['period']['type'] === 'overtime' ? 'overtime_ms' : 'period_ms'] = $this->integer($p['duration_ms'], 60000, 5940000);
                break;
            case 'game_clock.reset':
                $s['game_clock'] = ['remaining_ms' => $this->periodDuration($s), 'running' => false, 'deadline_ms' => null];
                break;
            case 'game_clock.set': case 'shot_clock.set':
                $key = explode('.', $type)[0];
                if ($s[$key]['running']) {
                    $this->invalid('Clock masih berjalan.');
                }
                $s[$key]['remaining_ms'] = $this->integer($p['remaining_ms'], 0, $key === 'game_clock' ? 5999000 : 99000);
                break;
            case 'shot_clock.reset':
                if (! in_array($p['duration_ms'], [24000, 14000, 12000], true)) {
                    $this->invalid('Preset shot hanya 24, 14, atau 12 detik.');
                }
                $s['shot_clock']['remaining_ms'] = $p['duration_ms'];
                $s['shot_clock']['deadline_ms'] = $s['shot_clock']['running'] ? $nowMs + $p['duration_ms'] : null;
                break;
            case 'shot_clock.adjust':
                if (! in_array($p['delta_ms'], [-1000, 1000], true)) {
                    $this->invalid('Koreksi shot hanya tambah atau kurang satu detik.');
                }
                $remaining = $this->integer($s['shot_clock']['remaining_ms'] + $p['delta_ms'], 0, 99000);
                $s['shot_clock']['remaining_ms'] = $remaining;
                $s['shot_clock']['deadline_ms'] = $s['shot_clock']['running'] ? $nowMs + $remaining : null;
                break;
            case 'shot_clock.display.set':
                if (! is_bool($p['enabled'])) {
                    $this->invalid('Enabled harus boolean.');
                }
                $s['shot_clock']['display_enabled'] = $p['enabled'];
                break;
            case 'timeout_clock.start':
                if (! in_array($p['duration_ms'], [30000, 60000], true)) {
                    $this->invalid('Timeout hanya 30 atau 60 detik.');
                }
                $this->stop($s, 'game_clock', $nowMs);
                $this->stop($s, 'shot_clock', $nowMs);
                $s['timeout_clock'] = ['remaining_ms' => $p['duration_ms'], 'running' => true, 'deadline_ms' => $nowMs + $p['duration_ms']];
                break;
            case 'possession.set':
                if (! in_array($p['team'], ['home', 'away', 'none'], true)) {
                    $this->invalid('Possession tidak sah.');
                }
                $s['possession'] = $p['team'];
                break;
            case 'period.set':
                if (! in_array($p['type'], ['regulation', 'overtime'], true)) {
                    $this->invalid('Jenis periode tidak sah.');
                }
                $target = ['type' => $p['type'], 'number' => $this->integer($p['number'], 1, $p['type'] === 'regulation' ? 4 : 99)];
                $s = $this->changePeriod($s, $target);
                break;
            case 'team_fouls.reset':
                $this->invalid('Untuk menolkan foul, lanjutkan periode. Koreksi salah input dengan Foul −.');
            case 'session.reset':
                $fresh = $this->initial($s['session_id'], $s['home']['name'], $s['away']['name'], $nowMs);
                $fresh['game_clock']['remaining_ms'] = $s['ruleset']['period_ms'];
                foreach (['home', 'away', 'period', 'period_history', 'period_active', 'unallocated_score', 'possession', 'game_clock', 'shot_clock', 'timeout_clock', 'signal'] as $key) {
                    if (in_array($key, ['home', 'away'], true)) {
                        $fresh[$key]['community_id'] = $s[$key]['community_id'];
                    }
                    $s[$key] = $fresh[$key];
                }
                break;
            case 'horn.trigger':
                $s['signal'] = $this->signal('horn_manual', $nowMs);
                break;
            case 'period.correct':
                $s = $this->correctPeriod($s, $p);
                break;
        }
        if ($before !== $s) {
            $s['revision']++;
        }

        return $s;
    }

    public function withLedger(array $s): array
    {
        if (! isset($s['period_active'])) {
            $s['period_history'] = [];
            $s['period_active'] = ['period' => $s['period']];
            foreach (['home', 'away'] as $team) {
                $s['period_active'][$team] = ['score' => 0, 'team_fouls' => $s[$team]['team_fouls']];
                $s['unallocated_score'][$team] = $s[$team]['score'];
            }
        }

        // First period has no earlier points: restored total belongs to the active period.
        if ($s['period']['type'] === 'regulation' && $s['period']['number'] === 1 && $s['period_history'] === []) {
            foreach (['home', 'away'] as $team) {
                $s['period_active'][$team]['score'] = $s[$team]['score'];
                $s['unallocated_score'][$team] = 0;
            }
        }

        return $s;
    }

    private function periodDuration(array $s): int
    {
        return $s['ruleset'][$s['period']['type'] === 'overtime' ? 'overtime_ms' : 'period_ms'];
    }

    private function correctPeriod(array $s, array $p): array
    {
        if (! in_array($p['reason'], ['Salah input', 'Keputusan wasit'], true)) {
            $this->invalid('Alasan koreksi tidak sah.');
        }
        if (! is_null($p['note']) && (! is_string($p['note']) || mb_strlen($p['note']) > 200)) {
            $this->invalid('Catatan koreksi tidak sah.');
        }
        if (! in_array($p['type'], ['regulation', 'overtime'], true)) {
            $this->invalid('Jenis periode tidak sah.');
        }
        $target = ['type' => $p['type'], 'number' => $this->integer($p['number'], 1, $p['type'] === 'regulation' ? 4 : 99)];

        foreach (['home_score', 'away_score'] as $field) {
            if (! is_int($p[$field]) || $p[$field] < 0 || $p[$field] > 999) {
                $this->invalid('Nilai skor di luar batas.');
            }
        }
        foreach (['home_fouls', 'away_fouls'] as $field) {
            if (! is_int($p[$field]) || $p[$field] < 0 || $p[$field] > 99) {
                $this->invalid('Nilai foul di luar batas.');
            }
        }

        if ($s['period']['type'] === $target['type'] && $s['period']['number'] === $target['number']) {
            $this->invalid('Periode aktif tidak dapat dikoreksi.');
        }

        $targetIdx = null;
        foreach ($s['period_history'] as $idx => $entry) {
            if ($entry['period']['type'] === $target['type'] && $entry['period']['number'] === $target['number']) {
                $targetIdx = $idx;
                break;
            }
        }
        if ($targetIdx === null) {
            $this->invalid('Catatan periode tidak ditemukan.');
        }

        $targetEntry = $s['period_history'][$targetIdx];
        $homeScoreDelta = $p['home_score'] - $targetEntry['home']['score'];
        $awayScoreDelta = $p['away_score'] - $targetEntry['away']['score'];
        $newHomeScore = $s['home']['score'] + $homeScoreDelta;
        $newAwayScore = $s['away']['score'] + $awayScoreDelta;

        if ($newHomeScore < 0 || $newHomeScore > 999 || $newAwayScore < 0 || $newAwayScore > 999) {
            $this->invalid('Nilai total skor di luar batas.');
        }

        $isTargetQ4OrOt = ($target['type'] === 'overtime' || ($target['type'] === 'regulation' && $target['number'] === 4));
        $isCurrentOt = ($s['period']['type'] === 'overtime');
        $carryOver = $isTargetQ4OrOt && $isCurrentOt;

        // ponytail: scan bounded regulation/overtime history; index only if the period limit grows.
        foreach (['home', 'away'] as $team) {
            $deltaFouls = $p[$team.'_fouls'] - $targetEntry[$team]['team_fouls'];
            $newArchivedDisplayFouls = ($targetEntry['display_fouls'][$team] ?? $targetEntry[$team]['team_fouls']) + $deltaFouls;
            if ($newArchivedDisplayFouls < 0 || $newArchivedDisplayFouls > 99) {
                $this->invalid('Nilai foul di luar batas.');
            }
            if ($carryOver) {
                for ($later = $targetIdx + 1; $later < count($s['period_history']); $later++) {
                    if ($s['period_history'][$later]['period']['type'] === 'overtime') {
                        $laterDisplay = $s['period_history'][$later]['display_fouls'][$team] + $deltaFouls;
                        if ($laterDisplay < 0 || $laterDisplay > 99) {
                            $this->invalid('Nilai foul di luar batas.');
                        }
                    }
                }
                $newActiveFouls = $s[$team]['team_fouls'] + $deltaFouls;
                if ($newActiveFouls < 0 || $newActiveFouls > 99) {
                    $this->invalid('Nilai foul di luar batas.');
                }
            }
        }

        $s['period_history'][$targetIdx]['home']['score'] = $p['home_score'];
        $s['period_history'][$targetIdx]['away']['score'] = $p['away_score'];
        $s['home']['score'] = $newHomeScore;
        $s['away']['score'] = $newAwayScore;

        foreach (['home', 'away'] as $team) {
            $deltaFouls = $p[$team.'_fouls'] - $targetEntry[$team]['team_fouls'];
            $s['period_history'][$targetIdx][$team]['team_fouls'] = $p[$team.'_fouls'];
            $s['period_history'][$targetIdx]['display_fouls'][$team] = ($targetEntry['display_fouls'][$team] ?? $targetEntry[$team]['team_fouls']) + $deltaFouls;

            if ($carryOver) {
                for ($later = $targetIdx + 1; $later < count($s['period_history']); $later++) {
                    if ($s['period_history'][$later]['period']['type'] === 'overtime') {
                        $s['period_history'][$later]['display_fouls'][$team] += $deltaFouls;
                    }
                }
                $s[$team]['team_fouls'] += $deltaFouls;
            }
        }

        return $s;
    }

    private function changePeriod(array $s, array $target): array
    {
        $index = fn (array $p): int => $p['number'] + ($p['type'] === 'overtime' ? 4 : 0);
        $step = $index($target) - $index($s['period']);
        if ($step === 0) {
            return $s;
        }
        if (abs($step) !== 1) {
            $this->invalid('Pindah satu periode setiap kali.');
        }
        if ($step < 0) {
            $previous = end($s['period_history']);
            if (! $previous || $index($previous['period']) !== $index($target)) {
                $this->invalid('Catatan periode sebelumnya tidak tersedia.');
            }
            array_pop($s['period_history']);
            $s['period_active'] = $previous;
            foreach (['home', 'away'] as $team) {
                $s[$team]['team_fouls'] = $previous['display_fouls'][$team];
                $s[$team]['score'] = $s['unallocated_score'][$team] + $previous[$team]['score']
                    + array_sum(array_column(array_column($s['period_history'], $team), 'score'));
                if (isset($previous['timeouts_used'][$team])) {
                    $s[$team]['timeouts_used'] = $previous['timeouts_used'][$team];
                }
            }
            $s['game_clock'] = ['remaining_ms' => $previous['remaining_ms'], 'running' => false, 'deadline_ms' => null];
        } else {
            $record = $s['period_active'];
            $record['remaining_ms'] = $s['game_clock']['remaining_ms'];
            $record['display_fouls'] = ['home' => $s['home']['team_fouls'], 'away' => $s['away']['team_fouls']];
            $record['timeouts_used'] = ['home' => $s['home']['timeouts_used'], 'away' => $s['away']['timeouts_used']];
            $s['period_history'][] = $record;
            $s['period_active'] = ['period' => $target, 'home' => ['score' => 0, 'team_fouls' => 0], 'away' => ['score' => 0, 'team_fouls' => 0]];
            if ($target['type'] === 'regulation') {
                $s['home']['team_fouls'] = $s['away']['team_fouls'] = 0;
            }
            $s['period'] = $target;
            $s['game_clock'] = ['remaining_ms' => $this->periodDuration($s), 'running' => false, 'deadline_ms' => null];
        }
        $s['period'] = $target;
        $s['shot_clock'] = ['remaining_ms' => 24000, 'running' => false, 'deadline_ms' => null, 'display_enabled' => $s['shot_clock']['display_enabled']];
        $s['timeout_clock'] = ['remaining_ms' => 0, 'running' => false, 'deadline_ms' => null];
        $s['signal'] = null;

        return $s;
    }

    private function start(array &$s, string $key, int $nowMs): void
    {
        if ($s[$key]['remaining_ms'] <= 0) {
            $this->invalid('Clock nol tidak dapat dimulai.');
        }
        if (! $s[$key]['running']) {
            $s[$key]['running'] = true;
            $s[$key]['deadline_ms'] = $nowMs + $s[$key]['remaining_ms'];
        }
    }

    private function stop(array &$s, string $key, int $nowMs): void
    {
        if ($s[$key]['running']) {
            $s[$key]['remaining_ms'] = max(0, $s[$key]['deadline_ms'] - $nowMs);
        }
        $s[$key]['running'] = false;
        $s[$key]['deadline_ms'] = null;
    }

    private function integer(mixed $value, int $min, int $max): int
    {
        if (! is_int($value) || $value < $min || $value > $max) {
            $this->invalid('Nilai clock/periode di luar batas.');
        }

        return $value;
    }

    private function signal(string $kind, int $nowMs): array
    {
        return ['signal_id' => (string) Str::uuid(), 'kind' => $kind, 'duration_ms' => 2000, 'expires_at_ms' => $nowMs + 2000];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['payload' => $message]);
    }
}
