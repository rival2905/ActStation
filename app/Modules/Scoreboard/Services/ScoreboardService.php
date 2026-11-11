<?php

declare(strict_types=1);

namespace App\Modules\Scoreboard\Services;

use App\Contracts\CommunityLookup;
use App\Contracts\DTOs\CommunitySummary;
use App\Contracts\ScoreboardSports;
use App\Modules\Scoreboard\Exceptions\ScoreboardConflict;
use App\Modules\Scoreboard\Models\ScoreboardCommand;
use App\Modules\Scoreboard\Models\ScoreboardSession;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScoreboardService
{
    public function __construct(private CommunityLookup $communities, private ScoreboardSports $sports, private BasketballEngine $engine) {}

    public function community(Request $request): CommunitySummary
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403);
        $community = $this->communities->findForUser((int) $user->id);
        abort_unless($community?->is_active && $this->communities->canManage((int) $user->id, $community->id), 403, 'Pengurus komunitas aktif diperlukan.');
        if (! $request->isMethodSafe()) {
            $context = $request->input('panel_community_id');
            abort_unless(is_scalar($context) && (string) $community->id === (string) $context, 422, 'Konteks komunitas berubah. Muat ulang halaman.');
        }

        return $community;
    }

    public function listing(Request $request): array
    {
        $community = $this->community($request);
        $filter = $request->query('sport_category_id');
        abort_if($filter !== null && (! is_string($filter) || ! ctype_digit($filter)), 422);
        $query = ScoreboardSession::where('community_id', $community->id)->when($filter !== null, fn ($q) => $q->where('sport_category_id', (int) $filter));

        return [
            'community' => $community, 'sports' => $this->sports->all(),
            'activeSessions' => (clone $query)->where('status', 'active')->latest()->get(),
            'history' => (clone $query)->where('status', 'finished')->whereNotNull('result')->latest('finished_at')->paginate(20)->withQueryString(),
        ];
    }

    public function create(Request $request, array $input): ScoreboardSession
    {
        $community = $this->community($request);
        if (! $this->sports->supports((int) $input['sport_category_id'])) {
            throw ValidationException::withMessages(['sport_category_id' => 'Scoreboard cabor ini belum tersedia.']);
        }

        return DB::transaction(function () use ($request, $input, $community) {
            $id = (string) Str::uuid();
            $home = $input['home_name'] ?? 'Home';
            $away = $input['away_name'] ?? 'Away';

            return ScoreboardSession::create([
                'id' => $id, 'community_id' => $community->id, 'sport_category_id' => $input['sport_category_id'],
                'created_by' => $request->user()->id, 'mode' => $input['mode'], 'kind' => $input['kind'],
                'home_name' => $home, 'away_name' => $away, 'location' => $input['location'] ?? null,
                'state' => $this->engine->initial($id, $home, $away, $this->nowMs()), 'started_at' => now(),
                'status' => 'active',
            ]);
        });
    }

    public function read(Request $request, string $id): ScoreboardSession
    {
        $community = $this->community($request);

        return DB::transaction(function () use ($community, $id) {
            $session = $this->locked($community->id, $id);
            $this->materialize($session);

            return $session;
        });
    }

    public function readDisplay(string $id): ScoreboardSession
    {
        return DB::transaction(function () use ($id) {
            $session = ScoreboardSession::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->materialize($session);

            return $session;
        });
    }

    public function displaySnapshot(ScoreboardSession $session): array
    {
        $snapshot = Arr::only($this->snapshot($session), ['id', 'status', 'server_time_ms']);
        $snapshot['state'] = Arr::only($session->state, ['revision', 'period', 'possession', 'signal']);
        foreach (['home', 'away'] as $team) {
            $snapshot['state'][$team] = Arr::only($session->state[$team], ['name', 'score', 'team_fouls', 'timeouts_used']);
        }
        foreach (['game_clock', 'shot_clock', 'timeout_clock'] as $clock) {
            $snapshot['state'][$clock] = Arr::only($session->state[$clock], ['remaining_ms', 'running', 'display_enabled']);
        }

        return $snapshot;
    }

    public function command(Request $request, string $id, array $input): array
    {
        $community = $this->community($request);

        return DB::transaction(function () use ($request, $community, $id, $input) {
            $session = $this->locked($community->id, $id);
            $fingerprint = $this->fingerprint($input);
            if ($receipt = $this->duplicate($session, $input['command_id'], $fingerprint)) {
                return $receipt;
            }
            $now = $this->nowMs();
            $this->materialize($session, $now);
            $state = $session->state;
            $reason = null;
            $status = 'rejected';
            $expiry = (int) Carbon::parse($input['expires_at'])->getTimestampMs();
            if ($input['session_id'] !== $session->id || $input['authority_epoch'] !== $state['authority_epoch'] || $input['schema_version'] !== $state['schema_version']) {
                $reason = 'Kontrak atau authority sesi tidak cocok.';
            } elseif ($session->status !== 'active') {
                $reason = 'Sesi sudah ditutup.';
            } elseif ($expiry <= $now) {
                $status = 'expired';
                $reason = 'Command kedaluwarsa; tinjau snapshot terbaru.';
            } elseif ($expiry > $now + 5000) {
                $reason = 'Masa berlaku command melebihi 5 detik.';
            } elseif ($input['expected_revision'] !== $state['revision']) {
                $reason = 'Revision berubah; tinjau snapshot terbaru.';
            } else {
                try {
                    $before = $state;
                    $state = $this->restoreLegacyPeriod($session, $state, $input['type'], $input['payload']);
                    $next = $this->engine->apply($state, $input['type'], $input['payload'], $now);
                    $index = fn (array $p): int => $p['number'] + ($p['type'] === 'overtime' ? 4 : 0);
                    $rollback = $input['type'] === 'period.set' && $index($next['period']) < $index($before['period']);
                    if ($rollback || ($input['type'] === 'period.correct' && $next['revision'] !== $before['revision'])) {
                        $summary = fn (array $s): array => [
                            'period' => $s['period'], 'active' => $s['period_active'],
                            'home' => Arr::only($s['home'], ['score', 'team_fouls']),
                            'away' => Arr::only($s['away'], ['score', 'team_fouls']),
                            'records' => array_map(fn (array $r): array => Arr::only($r, ['period', 'home', 'away']), $s['period_history']),
                        ];
                        $next['period_changes'] = $before['period_changes'] ?? [];
                        $next['period_changes'][] = ['action' => $rollback ? 'rollback' : 'correction',
                            'at' => now()->toISOString(), 'operator' => $request->user()->name,
                            'reason' => $input['payload']['reason'] ?? 'Kembali ke periode sebelumnya',
                            'note' => $input['payload']['note'] ?? '',
                            'before' => $summary($before), 'after' => $summary($next)];
                    }
                    $session->state = $next;
                    $session->save();
                    $status = 'applied';
                } catch (ValidationException $e) {
                    $reason = collect($e->errors())->flatten()->first() ?? 'Command tidak sah.';
                }
            }
            $response = ['command_id' => $input['command_id'], 'status' => $status, 'reason' => $reason,
                'revision' => $session->state['revision'], 'snapshot' => $this->snapshot($session)];
            $this->receipt($session, $request, $input['command_id'], $input['type'], $fingerprint, $response,
                ['reason' => $input['payload']['reason'] ?? null, 'expected_revision' => $input['expected_revision']]);

            return $response;
        });
    }

    public function finish(Request $request, string $id, array $input): array
    {
        $community = $this->community($request);

        return DB::transaction(function () use ($request, $community, $id, $input) {
            $session = $this->locked($community->id, $id);
            $fingerprint = $this->fingerprint(['operation' => 'finish'] + $input);
            if ($receipt = $this->duplicate($session, $input['command_id'], $fingerprint)) {
                return $receipt;
            }
            $this->materialize($session);
            $state = $session->state;
            if ($session->status !== 'active' || $input['expected_revision'] !== $state['revision']) {
                throw new ScoreboardConflict('Sesi sudah ditutup atau revision berubah. Muat ulang sebelum meninjau hasil.');
            }
            if ($state['game_clock']['running'] || $state['shot_clock']['running'] || $state['timeout_clock']['running']) {
                throw ValidationException::withMessages(['clocks' => 'Hentikan semua clocks sebelum menyelesaikan sesi.']);
            }
            $record = (bool) $input['record_result'];
            if ($session->mode === 'recorded' && ! $record) {
                throw ValidationException::withMessages(['record_result' => 'Pertandingan Tercatat wajib menyimpan hasil.']);
            }
            if ($record) {
                $session->home_name = $input['home_name'];
                $session->away_name = $input['away_name'];
                $session->kind = $input['kind'];
                $session->location = $input['location'] ?? $session->location;
                $state['home']['name'] = $input['home_name'];
                $state['away']['name'] = $input['away_name'];
                $session->result = [
                    'home' => ['name' => $session->home_name, 'score' => $state['home']['score']],
                    'away' => ['name' => $session->away_name, 'score' => $state['away']['score']],
                    'kind' => $session->kind, 'location' => $session->location, 'source' => 'virtual',
                    'status' => 'recorded', 'visibility' => 'private', 'recorded_by' => $request->user()->id,
                    'period_history' => [...$state['period_history'], $state['period_active']],
                    'unallocated_score' => $state['unallocated_score'],
                    'recorded_at' => now()->toISOString(),
                ];
            }
            $state['revision']++;
            $session->state = $state;
            $session->status = 'finished';
            $session->finished_at = now();
            $session->save();
            $response = $this->snapshot($session);
            $this->receipt($session, $request, $input['command_id'], 'session.finish', $fingerprint, $response);

            return $response;
        });
    }

    public function getReceipt(Request $request, string $id, string $command): array
    {
        $community = $this->community($request);
        $session = ScoreboardSession::where('community_id', $community->id)->findOrFail($id);

        return ScoreboardCommand::where('session_id', $session->id)->where('command_id', $command)->firstOrFail()->response;
    }

    public function snapshot(ScoreboardSession $session): array
    {
        $state = $this->engine->withLedger($session->state);
        foreach (['game_clock', 'shot_clock', 'timeout_clock'] as $clock) {
            unset($state[$clock]['deadline_ms']);
        }
        $time = $this->nowMs();
        $state['sample'] = ['authority_boot_id' => 'virtual-1', 'sample_sequence' => $time,
            'sampled_at' => now()->toISOString()];

        return ['id' => $session->id, 'community_id' => $session->community_id, 'sport_category_id' => $session->sport_category_id,
            'mode' => $session->mode, 'kind' => $session->kind, 'status' => $session->status,
            'home_name' => $session->home_name, 'away_name' => $session->away_name, 'location' => $session->location,
            'result' => $session->result, 'finished_at' => $session->finished_at?->toISOString(),
            'server_time_ms' => $time, 'state' => $state];
    }

    private function restoreLegacyPeriod(ScoreboardSession $session, array $state, string $type, array $payload): array
    {
        if ($type !== 'period.set' || count($payload) !== 2 || ! isset($payload['type'], $payload['number']) || ! is_int($payload['number']) || ! in_array($payload['type'], ['regulation', 'overtime'], true)) {
            return $state;
        }
        $index = fn (array $p): int => $p['number'] + ($p['type'] === 'overtime' ? 4 : 0);
        if ($index($state['period']) - $index($payload) !== 1 || $state['period_history'] !== [] || $state['game_clock']['running'] || $state['shot_clock']['running'] || $state['timeout_clock']['running']) {
            return $state;
        }
        // ponytail: legacy sessions lack period records; restore receipts, never invent a split.
        foreach (ScoreboardCommand::where('session_id', $session->id)->latest('id')->cursor() as $command) {
            $response = $command->response;
            if (($response['status'] ?? null) !== 'applied') {
                continue;
            }
            if ($command->type === 'session.reset') {
                break;
            }
            $saved = $response['snapshot']['state'] ?? null;
            if (! $saved || $saved['revision'] >= $state['revision'] || $index($saved['period']) !== $index($payload)) {
                continue;
            }
            $restored = $this->engine->withLedger($saved);
            foreach (['revision', 'authority_epoch', 'authority', 'ruleset', 'ruleset_id', 'ruleset_version', 'session_id'] as $key) {
                $restored[$key] = $state[$key];
            }
            foreach (['game_clock', 'shot_clock', 'timeout_clock'] as $key) {
                $restored[$key]['running'] = false;
                $restored[$key]['deadline_ms'] = null;
            }
            $restored['period_changes'] = $state['period_changes'] ?? [];
            $restored['signal'] = null;
            unset($restored['sample']);
            $restored['revision']++;

            return $restored;
        }

        return $state;
    }

    private function nowMs(): int
    {
        return (int) now()->getTimestampMs();
    }

    private function locked(int $communityId, string $id): ScoreboardSession
    {
        return ScoreboardSession::where('community_id', $communityId)->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function materialize(ScoreboardSession $session, ?int $nowMs = null): void
    {
        if ($session->status === 'active') {
            $previousRevision = $session->state['revision'];
            $session->state = $this->engine->materialize($session->state, $nowMs ?? $this->nowMs());
            // ponytail: polling materializes deadlines; persist expiry, never elapsed ticks.
            if ($session->state['revision'] !== $previousRevision) {
                $session->save();
            }
        }
    }

    private function duplicate(ScoreboardSession $session, string $id, string $fingerprint): ?array
    {
        $receipt = ScoreboardCommand::where('session_id', $session->id)->where('command_id', $id)->first();
        if ($receipt && ! hash_equals($receipt->fingerprint, $fingerprint)) {
            throw new ScoreboardConflict('Command ID sudah dipakai dengan isi berbeda.');
        }

        return $receipt?->response;
    }

    private function receipt(ScoreboardSession $session, Request $request, string $id, string $type, string $fingerprint, array $response, array $audit = []): void
    {
        ScoreboardCommand::create(['session_id' => $session->id, 'command_id' => $id, 'actor_id' => $request->user()->id,
            'type' => $type, 'fingerprint' => $fingerprint, 'response' => $response, 'audit' => $audit]);
    }

    private function fingerprint(array $input): string
    {
        $canonical = function (array $value) use (&$canonical): array {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $canonical($item);
                }
            }

            return $value;
        };

        return hash('sha256', json_encode($canonical($input), JSON_THROW_ON_ERROR));
    }
}
