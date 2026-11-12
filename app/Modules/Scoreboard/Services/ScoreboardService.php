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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScoreboardService
{
    public function __construct(private CommunityLookup $communities, private ScoreboardSports $sports, private BasketballEngine $engine) {}

    public function personalListing(Request $request): array
    {
        return ['sports' => $this->sports->all(), 'resumeId' => $request->session()->get('scoreboard_trial_id'),
            'personalSessions' => $request->user()?->is_active
                ? ScoreboardSession::whereNull('community_id')->where('created_by', $request->user()->id)->where('status', 'active')->latest()->get()
                : collect()];
    }

    public function createPersonal(Request $request, array $input): ScoreboardSession
    {
        if (! $this->sports->supports((int) $input['sport_category_id'])) {
            throw ValidationException::withMessages(['sport_category_id' => 'Scoreboard cabor ini belum tersedia.']);
        }
        abort_if($request->user() && ! $request->user()->is_active, 403);
        if (! $request->user() && $id = $request->session()->get('scoreboard_trial_id')) {
            return $this->readPersonal($request, $id);
        }
        $token = $request->session()->get('scoreboard_trial_token');
        if (! is_string($token)) {
            $token = Str::random(64);
            $request->session()->put('scoreboard_trial_token', $token);
        }
        $id = (string) Str::uuid();
        $home = $input['home_name'] ?? 'Home';
        $away = $input['away_name'] ?? 'Away';
        $state = $this->engine->initial($id, $home, $away, $this->nowMs());
        $state['_trial'] = ['token_hash' => hash('sha256', $token), 'starts_used' => 0];
        $session = ScoreboardSession::create(['id' => $id, 'community_id' => null,
            'sport_category_id' => $input['sport_category_id'], 'created_by' => $request->user()?->id,
            'mode' => 'quick', 'kind' => 'practice', 'home_name' => $home, 'away_name' => $away,
            'state' => $state, 'started_at' => now(), 'status' => 'active']);
        if (! $request->user()) {
            $request->session()->put('scoreboard_trial_id', $id);
        }

        return $session;
    }

    private function personalLocked(Request $request, string $id): ScoreboardSession
    {
        abort_if($request->user() && ! $request->user()->is_active, 403);
        $session = ScoreboardSession::whereNull('community_id')->whereKey($id)->lockForUpdate()->firstOrFail();
        if ($session->created_by !== null) {
            abort_unless($request->user() && (int) $session->created_by === (int) $request->user()->id, 403);
        } else {
            $token = $request->session()->get('scoreboard_trial_token');
            $hash = $session->state['_trial']['token_hash'] ?? null;
            abort_unless(is_string($token) && is_string($hash) && hash_equals($hash, hash('sha256', $token)), 403);
        }

        return $session;
    }

    public function readPersonal(Request $request, string $id): ScoreboardSession
    {
        return DB::transaction(function () use ($request, $id): ScoreboardSession {
            $session = $this->personalLocked($request, $id);
            $this->materialize($session);

            return $session;
        });
    }

    public function claimPersonal(Request $request, string $id): ScoreboardSession
    {
        abort_unless($request->user()?->is_active, 403);

        return DB::transaction(function () use ($request, $id): ScoreboardSession {
            $session = $this->personalLocked($request, $id);
            if ($session->created_by === null) {
                $session->update(['created_by' => $request->user()->id]);
                $request->session()->forget('scoreboard_trial_id');
            }

            return $session;
        });
    }

    public function personalReceipt(Request $request, string $id, string $command): array
    {
        return DB::transaction(function () use ($request, $id, $command): array {
            $session = $this->personalLocked($request, $id);

            return ScoreboardCommand::where('session_id', $session->id)->where('command_id', $command)->firstOrFail()->response;
        });
    }

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

    public function command(Request $request, string $id, array $input, bool $shotGuest = false, bool $personal = false): array
    {
        $community = $shotGuest || $personal ? null : $this->community($request);

        return DB::transaction(function () use ($request, $community, $id, $input, $shotGuest, $personal) {
            $session = $personal ? $this->personalLocked($request, $id)
                : ($shotGuest ? ScoreboardSession::whereKey($id)->lockForUpdate()->firstOrFail() : $this->locked($community->id, $id));
            if ($shotGuest) {
                $this->authorizeShot($request, $session);
                abort_unless(in_array($input['type'], ['shot_clock.start', 'shot_clock.stop', 'shot_clock.reset', 'shot_clock.adjust', 'shot_clock.display.set', 'timeout_clock.start', 'timeout_clock.stop', 'horn.trigger'], true), 403);
            }
            $fingerprint = $this->fingerprint($input);
            if ($receipt = $this->duplicate($session, $input['command_id'], $fingerprint)) {
                if ($shotGuest) {
                    $this->authorizeShotReceipt($request, $session, $input['command_id']);
                }

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
                    $starts = (int) ($state['_trial']['starts_used'] ?? 0);
                    if ($personal && ! $session->created_by && $input['type'] === 'game_clock.start'
                        && ! $state['game_clock']['running'] && $starts >= 2) {
                        throw ValidationException::withMessages(['trial' => 'Percobaan selesai. Daftar akun untuk melanjutkan scoreboard ini.']);
                    }
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
                            'at' => now()->toISOString(), 'operator' => $request->user()?->name ?? 'Pengunjung',
                            'reason' => $input['payload']['reason'] ?? 'Kembali ke periode sebelumnya',
                            'note' => $input['payload']['note'] ?? '',
                            'before' => $summary($before), 'after' => $summary($next)];
                    }
                    if (isset($before['shot_access'])) {
                        $next['shot_access'] = $before['shot_access'];
                    }
                    if (isset($before['_trial'])) {
                        $next['_trial'] = $before['_trial'];
                        if ($personal && ! $session->created_by && $input['type'] === 'game_clock.start'
                            && ! $before['game_clock']['running'] && $next['game_clock']['running']) {
                            $next['_trial']['starts_used'] = $starts + 1;
                        }
                    }
                    $session->state = $next;
                    $session->save();
                    $status = 'applied';
                } catch (ValidationException $e) {
                    $reason = collect($e->errors())->flatten()->first() ?? 'Command tidak sah.';
                }
            }
            $response = ['command_id' => $input['command_id'], 'status' => $status, 'reason' => $reason,
                'revision' => $session->state['revision'], 'snapshot' => $shotGuest ? $this->shotSnapshot($session) : $this->snapshot($session)];
            $this->receipt($session, $request, $input['command_id'], $input['type'], $fingerprint, $response,
                ['reason' => $input['payload']['reason'] ?? null, 'expected_revision' => $input['expected_revision']]
                    + ($shotGuest ? ['source' => 'shared-shot', 'grant_id' => $session->state['shot_access']['id']] : []));

            return $response;
        });
    }

    public function finish(Request $request, string $id, array $input, bool $personal = false): array
    {
        $community = $personal ? null : $this->community($request);

        return DB::transaction(function () use ($request, $community, $id, $input, $personal) {
            $session = $personal ? $this->personalLocked($request, $id) : $this->locked($community->id, $id);
            if ($personal) {
                abort_unless($session->created_by && $request->user()?->is_active, 403);
                abort_if((bool) $input['record_result'], 422, 'Histori komunitas memerlukan sesi komunitas dan kewenangan pengurus.');
            }
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
        unset($state['shot_access'], $state['_trial']);
        foreach (['game_clock', 'shot_clock', 'timeout_clock'] as $clock) {
            unset($state[$clock]['deadline_ms']);
        }
        $time = $this->nowMs();
        $state['sample'] = ['authority_boot_id' => 'virtual-1', 'sample_sequence' => $time,
            'sampled_at' => now()->toISOString()];

        return ['id' => $session->id, 'community_id' => $session->community_id, 'sport_category_id' => $session->sport_category_id,
            'mode' => $session->mode, 'kind' => $session->kind, 'status' => $session->status,
            'home_name' => $session->home_name, 'away_name' => $session->away_name, 'location' => $session->location,
            'shot_access' => ['expires_at' => $session->status === 'active' ? ($session->state['shot_access']['expires_at'] ?? null) : null],
            'result' => $session->result, 'finished_at' => $session->finished_at?->toISOString(),
            'server_time_ms' => $time, 'state' => $state]
            + ($session->community_id === null ? ['trial' => ['limited' => $session->created_by === null,
                'starts_used' => (int) ($session->state['_trial']['starts_used'] ?? 0),
                'starts_remaining' => $session->created_by === null ? max(0, 2 - (int) ($session->state['_trial']['starts_used'] ?? 0)) : null]] : []);
    }

    public function shareShot(Request $request, string $id, int $hours): array
    {
        $community = $this->community($request);

        return DB::transaction(function () use ($request, $community, $id, $hours): array {
            $session = $this->locked($community->id, $id);
            abort_unless($session->status === 'active', 409, 'Sesi sudah ditutup.');
            $state = $session->state;
            $state['shot_access'] = ['id' => (string) Str::uuid(), 'created_by' => $request->user()->id,
                'expires_at' => now()->addHours($hours)->toISOString()];
            $session->update(['state' => $state]);

            return $this->shotLinks($session);
        });
    }

    public function revokeShot(Request $request, string $id): array
    {
        $community = $this->community($request);

        return DB::transaction(function () use ($community, $id): array {
            $session = $this->locked($community->id, $id);
            $state = $session->state;
            unset($state['shot_access']);
            $session->update(['state' => $state]);

            return ['expires_at' => null];
        });
    }

    private function shotLinks(ScoreboardSession $session): array
    {
        $grant = $session->state['shot_access'];
        $sign = fn (string $route): string => URL::temporarySignedRoute($route, Carbon::parse($grant['expires_at']), ['session' => $session->id, 'grant' => $grant['id']]);

        return ['url' => $sign('panel.scoreboard.shot'), 'snapshot_url' => $sign('scoreboard.shot.snapshot'),
            'command_url' => $sign('scoreboard.shot.command'), 'receipt_url' => $sign('scoreboard.shot.receipt'), 'expires_at' => $grant['expires_at']];
    }

    private function authorizeShot(Request $request, ScoreboardSession $session): void
    {
        $grant = $session->state['shot_access'] ?? null;
        abort_unless($request->hasValidSignature() && $session->status === 'active' && $grant
            && is_string($request->query('grant')) && hash_equals($grant['id'], $request->query('grant'))
            && Carbon::parse($grant['expires_at'])->isFuture(), 410,
            'Akses shot clock sudah berakhir. Minta link baru kepada operator utama.');
        // Shared controller acts on a grant, not as a logged-in account. Audit retains issuer and grant ID.
        $request->attributes->set('scoreboard_shot_actor', $grant['created_by']);
    }

    public function shotPage(Request $request, string $id): array
    {
        return DB::transaction(function () use ($request, $id): array {
            $session = ScoreboardSession::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorizeShot($request, $session);
            $links = $this->shotLinks($session);

            return ['session' => $session, 'snapshotUrl' => $links['snapshot_url'], 'commandUrl' => $links['command_url'],
                'receiptUrl' => $links['receipt_url'], 'expiresAt' => $links['expires_at']];
        });
    }

    public function readShot(Request $request, string $id): array
    {
        return DB::transaction(function () use ($request, $id): array {
            $session = ScoreboardSession::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorizeShot($request, $session);
            $this->materialize($session);

            return $this->shotSnapshot($session);
        });
    }

    private function shotSnapshot(ScoreboardSession $session): array
    {
        $snapshot = $this->snapshot($session);
        foreach (['home', 'away'] as $team) {
            $snapshot['state'][$team] = Arr::only($snapshot['state'][$team], ['name', 'score', 'team_fouls', 'timeouts_used']);
        }

        return Arr::only($snapshot, ['id', 'status', 'server_time_ms']) + ['state' => Arr::only($snapshot['state'],
            ['session_id', 'schema_version', 'revision', 'authority_epoch', 'authority', 'sample', 'period', 'possession', 'signal', 'home', 'away', 'game_clock', 'shot_clock', 'timeout_clock'])];
    }

    private function authorizeShotReceipt(Request $request, ScoreboardSession $session, string $command): ScoreboardCommand
    {
        $receipt = ScoreboardCommand::where('session_id', $session->id)->where('command_id', $command)->firstOrFail();
        abort_unless(($receipt->audit['grant_id'] ?? null) === $session->state['shot_access']['id'], 403);

        return $receipt;
    }

    public function shotReceipt(Request $request, string $id): array
    {
        return DB::transaction(function () use ($request, $id): array {
            $session = ScoreboardSession::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorizeShot($request, $session);
            $command = $request->header('X-Scoreboard-Command');
            abort_unless(is_string($command) && Str::isUuid($command), 422);

            return $this->authorizeShotReceipt($request, $session, $command)->response;
        });
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
        ScoreboardCommand::create(['session_id' => $session->id, 'command_id' => $id, 'actor_id' => $request->attributes->get('scoreboard_shot_actor') ?? $request->user()?->id,
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
