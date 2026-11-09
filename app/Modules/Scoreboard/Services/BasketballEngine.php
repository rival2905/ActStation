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
            'ruleset' => ['regulation_periods' => 4, 'period_ms' => 600000, 'overtime_ms' => 300000, 'shot_presets_ms' => [24000, 14000], 'timeouts_max' => 5, 'training_only' => true],
            'home' => $team($home), 'away' => $team($away), 'period' => ['type' => 'regulation', 'number' => 1],
            'possession' => 'none', 'game_clock' => $clock(600000), 'shot_clock' => $clock(24000) + ['display_enabled' => true],
            'timeout_clock' => $clock(0), 'signal' => null];
    }

    public function materialize(array $s, int $nowMs): array
    {
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
            'shot_clock.reset', 'timeout_clock.start' => ['duration_ms'],
            'shot_clock.display.set' => ['enabled'], 'possession.set' => ['team'],
            'period.set' => ['type', 'number'], 'team_fouls.reset', 'session.reset' => ['confirmed', 'reason'],
            'game_clock.start', 'game_clock.stop', 'shot_clock.start', 'shot_clock.stop', 'timeout_clock.stop', 'horn.trigger' => [],
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
        if (in_array($type, ['game_clock.set', 'period.set', 'team_fouls.reset', 'session.reset'], true)) {
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
                $s[$team][$field] = $value;
                break;
            case 'game_clock.start':
                if ($s['timeout_clock']['running']) {
                    $this->invalid('Timeout masih berjalan.');
                }
                $this->start($s, 'game_clock', $nowMs);
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
            case 'game_clock.set': case 'shot_clock.set':
                $key = explode('.', $type)[0];
                if ($s[$key]['running']) {
                    $this->invalid('Clock masih berjalan.');
                }
                $s[$key]['remaining_ms'] = $this->integer($p['remaining_ms'], 0, $key === 'game_clock' ? 5999000 : 99000);
                break;
            case 'shot_clock.reset':
                if (! in_array($p['duration_ms'], [24000, 14000], true)) {
                    $this->invalid('Preset shot hanya 24 atau 14 detik.');
                }
                $s['shot_clock']['remaining_ms'] = $p['duration_ms'];
                $s['shot_clock']['deadline_ms'] = $s['shot_clock']['running'] ? $nowMs + $p['duration_ms'] : null;
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
                $s['period'] = ['type' => $p['type'], 'number' => $this->integer($p['number'], 1, $p['type'] === 'regulation' ? 4 : 99)];
                break;
            case 'team_fouls.reset':
                $s['home']['team_fouls'] = $s['away']['team_fouls'] = 0;
                break;
            case 'session.reset':
                $fresh = $this->initial($s['session_id'], $s['home']['name'], $s['away']['name'], $nowMs);
                foreach (['home', 'away', 'period', 'possession', 'game_clock', 'shot_clock', 'timeout_clock', 'signal'] as $key) {
                    if (in_array($key, ['home', 'away'], true)) {
                        $fresh[$key]['community_id'] = $s[$key]['community_id'];
                    }
                    $s[$key] = $fresh[$key];
                }
                break;
            case 'horn.trigger':
                $s['signal'] = $this->signal('horn_manual', $nowMs);
                break;
        }
        if ($before !== $s) {
            $s['revision']++;
        }

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
