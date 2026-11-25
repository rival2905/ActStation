<?php

declare(strict_types=1);

use App\Models\AgentOfficePrincipal;
use App\Models\User;
use App\Services\AgentOfficeSecurity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function relayFixture(): array
{
    agentOfficeFixtureGuard();
    DB::beginTransaction();
    $dir = str_replace('\\', '/', getenv('TMPDIR')).'/relay-'.bin2hex(random_bytes(8));
    mkdir($dir, 0700, true);
    config(['agent_office.enabled' => true, 'agent_office.allow_loopback_http' => true, 'agent_office.social_relay_enabled' => true,
        'agent_office.master_key' => base64_encode(random_bytes(32)), 'agent_office.registry_path' => $dir.'/registry.enc',
        'agent_office.state_path' => $dir.'/state.json', 'agent_office.audit_path' => $dir.'/audit.jsonl']);
    $principals = [];
    $keys = [];
    foreach (['collector', 'office', 'sender'] as $role) {
        $principal = AgentOfficePrincipal::create(['name' => 'Synthetic relay '.$role, 'enabled' => true]);
        $token = $principal->createToken('fixture', ['agent:social-'.$role], now()->addHour())->plainTextToken;
        $secret = random_bytes(32);
        $principals[$role] = compact('principal', 'token', 'secret');
        $keys[$role] = ['secret' => base64_encode($secret), 'principal_id' => $principal->id, 'expires_at' => time() + 3600, 'enabled' => true];
    }
    app(AgentOfficeSecurity::class)->publish(['version' => 1, 'enabled' => true, 'expires_at' => time() + 3600,
        'allowlist' => [['cidr' => '127.0.0.1/32', 'expires_at' => null]], 'keys' => $keys]);

    return compact('dir', 'principals');
}

function relayCall($test, array $fixture, string $role, string $action, array $payload, array $replace = [])
{
    $target = '/api/v1/internal/agent-office/social-relay/'.$action;
    $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $identity = $fixture['principals'][$role];
    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(32));
    $idempotency = (string) Str::uuid();
    $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Authorization' => 'Bearer '.$identity['token'],
        'X-Agent-Key-Id' => $role, 'X-Agent-Timestamp' => $timestamp, 'X-Agent-Nonce' => $nonce, 'Idempotency-Key' => $idempotency,
        'X-Agent-Signature' => app(AgentOfficeSecurity::class)->signature('POST', $target, $timestamp, $nonce, $role, $identity['token'], $body, $idempotency, $identity['secret'])];
    $headers = array_replace($headers, $replace);
    $server = ['REMOTE_ADDR' => '127.0.0.1'];
    foreach ($headers as $key => $value) {
        $server[in_array($key, ['Content-Type', 'Content-Length']) ? strtoupper(str_replace('-', '_', $key)) : 'HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
    }

    return $test->call('POST', $target, [], [], [], $server, $body);
}

function relayCleanup(array $fixture): void
{
    DB::rollBack();
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture['dir'], FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($fixture['dir']);
}

function relayComment(): array
{
    return ['account_ref' => 'actstationcontinent', 'platform' => 'instagram', 'media' => ['id' => '123', 'permalink' => 'https://www.instagram.com/p/synthetic/', 'published_at' => now()->subHour()->toIso8601String()],
        'comments' => [['id' => '456', 'text' => 'synthetic-comment-canary', 'timestamp' => now()->subMinute()->toIso8601String()]], 'page' => 1];
}

test('relay rejects foreign fields and preserves stable sender identifiers exactly', function () {
    $f = relayFixture();
    try {
        $body = ['channel' => 'comments', 'account_ref' => 'actstationcontinent', 'delivery_id' => (string) Str::uuid(), 'payload' => relayComment()];
        $body['payload']['comments'][0]['sender_id'] = '00123';
        relayCall($this, $f, 'collector', 'publish', $body)->assertCreated();
        $pulled = relayCall($this, $f, 'office', 'pull', ['channel' => 'comments', 'account_ref' => 'actstationcontinent'])->assertOk()->json('data');
        expect($pulled['payload']['comments'][0]['sender_id'])->toBe('00123');
        $body['delivery_id'] = (string) Str::uuid();
        $body['payload']['comments'][0]['sender_id'] = "123\n";
        relayCall($this, $f, 'collector', 'publish', $body)->assertUnprocessable();
        $body['payload']['comments'][0]['sender_id'] = '123';
        relayCall($this, $f, 'collector', 'publish', $body + ['admin' => true])->assertUnprocessable();
        $body['payload']['comments'][0]['admin'] = true;
        relayCall($this, $f, 'collector', 'publish', $body)->assertUnprocessable();
    } finally {
        relayCleanup($f);
    }
});

function relayDispatch(string $comment = '456', array $replace = []): array
{
    $payload = ['item_id' => (string) Str::uuid(), 'account_ref' => 'actstationcontinent', 'platform' => 'instagram', 'account_id' => '17841480919882521',
        'comment_id' => $comment, 'media_id' => '123', 'lane' => 'reply_only', 'reply_text' => 'Synthetic reply', 'dm_text' => null, 'lease_token' => str_repeat('a', 64)];

    return array_replace(['channel' => 'dispatch', 'account_ref' => 'actstationcontinent', 'delivery_id' => (string) Str::uuid(), 'not_before' => now()->toIso8601String(), 'payload' => $payload], $replace);
}

function relayYoutubeComment(): array
{
    $payload = relayComment();
    $payload['account_ref'] = 'actstationcontinent-youtube';
    $payload['platform'] = 'youtube';
    $payload['media']['id'] = 'AbCdeF0_-12';
    $payload['media']['permalink'] = 'https://www.youtube.com/watch?v=AbCdeF0_-12';
    $payload['comments'][0]['id'] = 'Ugx_SYN-1.reply_2';
    $payload['comments'][0]['username'] = str_repeat('u', 80);
    $payload['comments'][0]['sender_id'] = 'UCAuz2caKezp1CVrJ0HRUpsA';

    return $payload;
}

function relayYoutubeDispatch(): array
{
    $body = relayDispatch('Ugx_SYN-1.reply_2');
    $body['account_ref'] = $body['payload']['account_ref'] = 'actstationcontinent-youtube';
    $body['payload']['platform'] = 'youtube';
    $body['payload']['account_id'] = 'UCAuz2caKezp1CVrJ0HRUpsA';
    $body['payload']['media_id'] = 'AbCdeF0_-12';

    return $body;
}

test('youtube relay comments deduplicate and pull without crossing instagram accounts', function () {
    $f = relayFixture();
    try {
        $body = ['channel' => 'comments', 'account_ref' => 'actstationcontinent-youtube', 'delivery_id' => (string) Str::uuid(), 'payload' => relayYoutubeComment()];
        $body['payload']['comments'][] = ['id' => '456', 'text' => 'Same literal ID, separate account', 'timestamp' => now()->subMinute()->toIso8601String()];
        relayCall($this, $f, 'collector', 'publish', $body)->assertCreated();
        relayCall($this, $f, 'collector', 'publish', $body)->assertOk()->assertJsonPath('data.duplicate', true);
        $instagram = array_replace($body, ['account_ref' => 'actstationcontinent', 'payload' => relayComment()]);
        relayCall($this, $f, 'collector', 'publish', $instagram)->assertCreated();
        expect(DB::table('social_relay_messages')->where('delivery_id', $body['delivery_id'])->count())->toBe(2)
            ->and(DB::table('social_relay_traces')->where('comment_id', '456')->count())->toBe(2);
        $query = ['channel' => 'comments', 'account_ref' => 'actstationcontinent-youtube'];
        $claim = relayCall($this, $f, 'office', 'pull', $query)->assertOk()->json('data');
        expect($claim['payload'])->toEqual($body['payload']);
        relayCall($this, $f, 'office', 'ack', $query + ['delivery_id' => $body['delivery_id'], 'claim_token' => $claim['claim_token']])->assertOk();
        relayCall($this, $f, 'office', 'pull', $query)->assertNoContent();
        expect(relayCall($this, $f, 'office', 'pull', ['channel' => 'comments', 'account_ref' => 'actstationcontinent'])->assertOk()->json('data.payload.platform'))->toBe('instagram');
        $body['payload']['comments'][0]['text'] = 'Changed';
        relayCall($this, $f, 'collector', 'publish', $body)->assertConflict();
        Http::assertNothingSent();
    } finally {
        relayCleanup($f);
    }
});

test('youtube relay dispatch and nonnumeric receipt retain ids and reply only state', function () {
    $f = relayFixture();
    try {
        $body = relayYoutubeDispatch();
        relayCall($this, $f, 'collector', 'publish', $body)->assertForbidden();
        relayCall($this, $f, 'office', 'publish', $body)->assertCreated();
        relayCall($this, $f, 'office', 'publish', $body)->assertOk()->assertJsonPath('data.duplicate', true);
        relayCall($this, $f, 'sender', 'pull', ['channel' => 'dispatch', 'account_ref' => 'actstationcontinent'])->assertNoContent();
        $query = ['channel' => 'dispatch', 'account_ref' => 'actstationcontinent-youtube'];
        $claim = relayCall($this, $f, 'sender', 'pull', $query)->assertOk()->json('data');
        expect($claim['payload'])->toEqual($body['payload']);
        relayCall($this, $f, 'sender', 'ack', $query + ['delivery_id' => $body['delivery_id'], 'claim_token' => $claim['claim_token']])->assertOk();
        $receipt = ['channel' => 'receipts', 'account_ref' => 'actstationcontinent-youtube', 'delivery_id' => (string) Str::uuid(),
            'payload' => ['account_ref' => 'actstationcontinent-youtube', 'item_id' => $body['payload']['item_id'], 'lease_token' => $body['payload']['lease_token'],
                'reply' => ['state' => 'sent', 'id' => str_repeat('R', 78).'_-'], 'dm' => ['state' => 'skipped', 'receipt_present' => false]]];
        relayCall($this, $f, 'sender', 'publish', $receipt)->assertCreated();
        relayCall($this, $f, 'sender', 'publish', $receipt)->assertOk()->assertJsonPath('data.duplicate', true);
        $trace = DB::table('social_relay_traces')->where('account_ref', 'actstationcontinent-youtube')->where('item_id', $body['payload']['item_id'])->first();
        expect($trace)->status->toBe('terkirim')->reply_id->toBe($receipt['payload']['reply']['id'])->dm_state->toBe('skipped')->platform->toBe('youtube');
        relayCall($this, $f, 'office', 'pull', ['channel' => 'receipts', 'account_ref' => 'actstationcontinent'])->assertNoContent();
        $query = ['channel' => 'receipts', 'account_ref' => 'actstationcontinent-youtube'];
        $claim = relayCall($this, $f, 'office', 'pull', $query)->assertOk()->json('data');
        expect($claim['payload'])->toEqual($receipt['payload']);
        relayCall($this, $f, 'office', 'ack', $query + ['delivery_id' => $receipt['delivery_id'], 'claim_token' => $claim['claim_token']])->assertOk();
        Http::assertNothingSent();
    } finally {
        relayCleanup($f);
    }
});

test('youtube relay rejects mismatched comments platform account video url and invalid identifiers', function () {
    $f = relayFixture();
    try {
        $body = ['channel' => 'comments', 'account_ref' => 'actstationcontinent-youtube', 'delivery_id' => (string) Str::uuid(), 'payload' => relayYoutubeComment()];
        foreach ([
            ['platform', 'instagram'], ['account_ref', 'actstationcontinent'],
            ['media.id', 'AbCdeF0_-1'], ['media.id', "AbCdeF0_-12\n"],
            ['media.permalink', 'https://www.youtube.com/watch?v=AbCdeF0_-13'],
            ['media.permalink', 'https://youtu.be/AbCdeF0_-12'],
            ['media.permalink', 'https://www.youtube.com/watch?v=AbCdeF0_-12&feature=share'],
            ['media.permalink', "https://www.youtube.com/watch?v=AbCdeF0_-12\n"],
            ['comments.0.id', str_repeat('x', 81)], ['comments.0.id', 'Ugx+bad'], ['comments.0.id', "Ugx_valid\n"],
            ['comments.0.sender_id', '17841480919882521'], ['comments.0.sender_id', "UCAuz2caKezp1CVrJ0HRUpsA\n"],
            ['comments.0.username', str_repeat('u', 81)],
        ] as [$field, $value]) {
            $bad = $body;
            data_set($bad['payload'], $field, $value);
            relayCall($this, $f, 'collector', 'publish', $bad)->assertUnprocessable()->assertJsonValidationErrors('payload.'.$field);
        }
        $body['payload']['comments'][0]['id'] = str_repeat('C', 78).'_-';
        unset($body['payload']['comments'][0]['sender_id']);
        relayCall($this, $f, 'collector', 'publish', $body)->assertCreated();
        $instagram = ['channel' => 'comments', 'account_ref' => 'actstationcontinent', 'delivery_id' => (string) Str::uuid(), 'payload' => relayComment()];
        $instagram['payload']['platform'] = 'youtube';
        relayCall($this, $f, 'collector', 'publish', $instagram)->assertUnprocessable()->assertJsonValidationErrors('payload.platform');
        Http::assertNothingSent();
    } finally {
        relayCleanup($f);
    }
});

test('youtube relay rejects mismatched dispatch platform channel ids and dm requests', function () {
    $f = relayFixture();
    try {
        $body = relayYoutubeDispatch();
        foreach ([
            ['platform', 'instagram'], ['account_ref', 'sircuan'], ['account_id', 'UC0000000000000000000000'],
            ['account_id', "UCAuz2caKezp1CVrJ0HRUpsA\n"], ['media_id', '123'], ['media_id', "AbCdeF0_-12\n"],
            ['comment_id', str_repeat('x', 81)], ['comment_id', 'Ugx+bad'], ['comment_id', "Ugx_valid\n"],
            ['lane', 'reply_dm'], ['dm_text', 'No YouTube DM'],
        ] as [$field, $value]) {
            $bad = $body;
            $bad['payload'][$field] = $value;
            relayCall($this, $f, 'office', 'publish', $bad)->assertUnprocessable()->assertJsonValidationErrors('payload.'.$field);
        }
        unset($body['payload']['dm_text']);
        relayCall($this, $f, 'office', 'publish', $body)->assertUnprocessable()->assertJsonValidationErrors('payload.dm_text');
        $instagram = relayDispatch();
        $instagram['payload']['platform'] = 'youtube';
        relayCall($this, $f, 'office', 'publish', $instagram)->assertUnprocessable()->assertJsonValidationErrors('payload.platform');
        Http::assertNothingSent();
    } finally {
        relayCleanup($f);
    }
});

test('youtube relay receipt derives id rules from account and refuses dm evidence', function () {
    $f = relayFixture();
    try {
        $body = ['channel' => 'receipts', 'account_ref' => 'actstationcontinent-youtube', 'delivery_id' => (string) Str::uuid(),
            'payload' => ['account_ref' => 'actstationcontinent-youtube', 'item_id' => (string) Str::uuid(), 'lease_token' => str_repeat('a', 64),
                'reply' => ['state' => 'sent', 'id' => 'Ugx_REPLY-1.2'], 'dm' => ['state' => 'skipped', 'receipt_present' => false]]];
        foreach ([
            ['reply.id', str_repeat('x', 81)], ['reply.id', 'Ugx+bad'], ['reply.id', "Ugx_valid\n"],
            ['dm.state', 'sent'], ['dm.state', 'failed'], ['dm.state', 'unknown'],
            ['dm.receipt_present', true], ['dm.receipt_present', 0], ['dm.receipt_present', '0'],
            ['account_ref', 'actstationcontinent'], ['platform', 'youtube'], ['error_code', 'untrusted_vendor_detail'],
            ['error_code', 'meta_rejected'], ['error_code', 'meta_rate_limited'],
        ] as [$field, $value]) {
            $bad = $body;
            data_set($bad['payload'], $field, $value);
            relayCall($this, $f, 'sender', 'publish', $bad)->assertUnprocessable()->assertJsonValidationErrors($field === 'platform' ? 'payload' : 'payload.'.$field);
        }
        foreach (['youtube_rejected', 'youtube_rate_limited', 'transport_timeout', 'readback_mismatch', 'transport_failed'] as $code) {
            $body['delivery_id'] = (string) Str::uuid();
            $body['payload']['reply'] = ['state' => 'failed'];
            $body['payload']['error_code'] = $code;
            relayCall($this, $f, 'sender', 'publish', $body)->assertCreated();
        }
        $body['account_ref'] = $body['payload']['account_ref'] = 'actstationcontinent';
        $body['payload']['reply'] = ['state' => 'sent', 'id' => 'Ugx_REPLY-1.2'];
        relayCall($this, $f, 'sender', 'publish', $body)->assertUnprocessable()->assertJsonValidationErrors('payload.reply.id');
        Http::assertNothingSent();
    } finally {
        relayCleanup($f);
    }
});

test('relay claimed dispatch never redelivers after expiry or lost acknowledgement', function () {
    $f = relayFixture();
    try {
        $body = relayDispatch();
        relayCall($this, $f, 'collector', 'publish', $body)->assertForbidden();
        relayCall($this, $f, 'office', 'publish', $body)->assertCreated();
        $query = ['channel' => 'dispatch', 'account_ref' => 'actstationcontinent'];
        $claim = relayCall($this, $f, 'sender', 'pull', $query)->assertOk()->json('data');
        expect(Carbon::parse($claim['claim_expires_at'])->diffInSeconds(now(), true))->toBeLessThanOrEqual(121);
        relayCall($this, $f, 'sender', 'pull', $query)->assertNoContent();
        DB::table('social_relay_messages')->where('delivery_id', $body['delivery_id'])->update(['claim_expires_at' => now()->subSecond()]);
        relayCall($this, $f, 'sender', 'pull', $query)->assertNoContent();
        expect(DB::table('social_relay_messages')->where('delivery_id', $body['delivery_id'])->value('status'))->toBe('unknown');
    } finally {
        relayCleanup($f);
    }
});

test('scheduled dispatch waits without expiry, releases when due, keeps a 45 second gap and traces each comment', function () {
    $f = relayFixture();
    try {
        $id = (string) Str::uuid();
        relayCall($this, $f, 'collector', 'publish', ['channel' => 'comments', 'account_ref' => 'actstationcontinent', 'delivery_id' => $id, 'payload' => relayComment()])->assertCreated();
        $query = ['channel' => 'comments', 'account_ref' => 'actstationcontinent'];
        $pulled = relayCall($this, $f, 'office', 'pull', $query)->assertOk()->json('data');
        relayCall($this, $f, 'office', 'ack', $query + ['delivery_id' => $id, 'claim_token' => $pulled['claim_token']])->assertOk();
        $first = relayDispatch('456', ['not_before' => now()->addSeconds(45)->toIso8601String()]);
        $second = relayDispatch('457', ['not_before' => now()->addSeconds(90)->toIso8601String()]);
        $second['payload']['lease_token'] = str_repeat('b', 64);
        relayCall($this, $f, 'office', 'publish', $first)->assertCreated();
        relayCall($this, $f, 'office', 'publish', $second)->assertCreated();
        relayCall($this, $f, 'office', 'publish', $first)->assertOk()->assertJsonPath('data.duplicate', true);
        relayCall($this, $f, 'office', 'publish', array_replace($first, ['not_before' => now()->addMinute()->toIso8601String()]))->assertConflict();
        $trace = DB::table('social_relay_traces')->where('comment_id', '456')->first();
        expect($trace->status)->toBe('terjadwal')->and($trace->office_received_at)->not->toBeNull()
            ->and($trace->comment_text)->not->toContain('synthetic-comment-canary')->and($trace->reply_text)->not->toContain('Synthetic reply');
        $dispatch = ['channel' => 'dispatch', 'account_ref' => 'actstationcontinent'];
        relayCall($this, $f, 'sender', 'pull', $dispatch)->assertNoContent();
        $this->travel(50)->seconds();
        $claim = relayCall($this, $f, 'sender', 'pull', $dispatch)->assertOk()->json('data');
        expect($claim['payload']['comment_id'])->toBe('456')->and(array_key_exists('lease_expires_at', $claim['payload']))->toBeFalse();
        expect(DB::table('social_relay_traces')->where('comment_id', '456')->value('status'))->toBe('dieksekusi');
        relayCall($this, $f, 'sender', 'pull', $dispatch)->assertNoContent();
        relayCall($this, $f, 'sender', 'ack', $dispatch + ['delivery_id' => $first['delivery_id'], 'claim_token' => $claim['claim_token']])->assertOk();
        $receipt = ['channel' => 'receipts', 'account_ref' => 'actstationcontinent', 'delivery_id' => (string) Str::uuid(),
            'payload' => ['account_ref' => 'actstationcontinent', 'item_id' => $first['payload']['item_id'], 'lease_token' => $first['payload']['lease_token'],
                'reply' => ['state' => 'sent', 'id' => '789'], 'dm' => ['state' => 'skipped', 'receipt_present' => false]]];
        relayCall($this, $f, 'sender', 'publish', $receipt)->assertCreated();
        expect(DB::table('social_relay_traces')->where('comment_id', '456')->first())->status->toBe('terkirim')->reply_id->toBe('789');
        $this->travel(46)->seconds();
        expect(relayCall($this, $f, 'sender', 'pull', $dispatch)->assertOk()->json('data.payload.comment_id'))->toBe('457');
        Http::assertNothingSent();
    } finally {
        relayCleanup($f);
    }
});

test('reply_dm dispatch carries the DM to the sender and a sent DM receipt traces as terkirim', function () {
    $f = relayFixture();
    try {
        $body = relayDispatch();
        $body['payload']['lane'] = 'reply_dm';
        $body['payload']['dm_text'] = 'Perkenalkan, saya Kenari dari ActStation Continent. Request rumahmu sudah kami catat.';
        relayCall($this, $f, 'office', 'publish', $body)->assertCreated();
        $dispatch = ['channel' => 'dispatch', 'account_ref' => 'actstationcontinent'];
        $claim = relayCall($this, $f, 'sender', 'pull', $dispatch)->assertOk()->json('data');
        expect($claim['payload'])->toMatchArray(['lane' => 'reply_dm', 'dm_text' => $body['payload']['dm_text']]);
        relayCall($this, $f, 'sender', 'ack', $dispatch + ['delivery_id' => $body['delivery_id'], 'claim_token' => $claim['claim_token']])->assertOk();
        relayCall($this, $f, 'sender', 'publish', ['channel' => 'receipts', 'account_ref' => 'actstationcontinent', 'delivery_id' => (string) Str::uuid(),
            'payload' => ['account_ref' => 'actstationcontinent', 'item_id' => $body['payload']['item_id'], 'lease_token' => $body['payload']['lease_token'],
                'reply' => ['state' => 'sent', 'id' => '789'], 'dm' => ['state' => 'sent', 'receipt_present' => true]]])->assertCreated();
        expect(DB::table('social_relay_traces')->where('comment_id', '456')->first())->status->toBe('terkirim')->dm_state->toBe('sent')->lane->toBe('reply_dm');
        Http::assertNothingSent();
    } finally {
        relayCleanup($f);
    }
});

test('sircuan has its own mailbox: comments and dispatch never cross accounts, unknown accounts are refused', function () {
    $f = relayFixture();
    try {
        $comment = array_replace(relayComment(), ['account_ref' => 'sircuan']);
        relayCall($this, $f, 'collector', 'publish', ['channel' => 'comments', 'account_ref' => 'sircuan', 'delivery_id' => (string) Str::uuid(), 'payload' => $comment])->assertCreated();
        relayCall($this, $f, 'office', 'pull', ['channel' => 'comments', 'account_ref' => 'actstationcontinent'])->assertNoContent();
        expect(relayCall($this, $f, 'office', 'pull', ['channel' => 'comments', 'account_ref' => 'sircuan'])->assertOk()->json('data.payload.account_ref'))->toBe('sircuan');
        $body = relayDispatch('457');
        $body['account_ref'] = $body['payload']['account_ref'] = 'sircuan';
        $body['payload']['account_id'] = '17841467588692125';
        relayCall($this, $f, 'office', 'publish', $body)->assertCreated();
        relayCall($this, $f, 'sender', 'pull', ['channel' => 'dispatch', 'account_ref' => 'actstationcontinent'])->assertNoContent();
        expect(relayCall($this, $f, 'sender', 'pull', ['channel' => 'dispatch', 'account_ref' => 'sircuan'])->assertOk()->json('data.payload.comment_id'))->toBe('457')
            ->and(DB::table('social_relay_traces')->where('comment_id', '457')->value('account_ref'))->toBe('sircuan');
        relayCall($this, $f, 'collector', 'publish', ['channel' => 'comments', 'account_ref' => 'sir.cuan', 'delivery_id' => (string) Str::uuid(),
            'payload' => array_replace(relayComment(), ['account_ref' => 'sir.cuan'])])->assertUnprocessable();
        $mixed = relayDispatch('458');
        $mixed['account_ref'] = 'sircuan';
        relayCall($this, $f, 'office', 'publish', $mixed)->assertUnprocessable();
        Http::assertNothingSent();
    } finally {
        relayCleanup($f);
    }
});

test('relay permissions isolate collector office and sender and reject invalid signatures', function () {
    $f = relayFixture();
    try {
        relayCall($this, $f, 'collector', 'pull', ['channel' => 'comments', 'account_ref' => 'actstationcontinent'])->assertForbidden();
        relayCall($this, $f, 'sender', 'pull', ['channel' => 'comments', 'account_ref' => 'actstationcontinent'])->assertForbidden();
        relayCall($this, $f, 'office', 'pull', ['channel' => 'comments', 'account_ref' => 'other'])->assertUnprocessable();
        relayCall($this, $f, 'office', 'pull', ['channel' => 'comments', 'account_ref' => 'actstationcontinent'], ['X-Agent-Signature' => str_repeat('0', 64)])->assertForbidden();
        relayCall($this, $f, 'office', 'pull', ['channel' => 'comments', 'account_ref' => 'actstationcontinent'], ['X-Agent-Timestamp' => '1000000000'])->assertForbidden();
        config(['agent_office.social_relay_enabled' => false]);
        relayCall($this, $f, 'office', 'pull', ['channel' => 'comments', 'account_ref' => 'actstationcontinent'])->assertForbidden();
    } finally {
        relayCleanup($f);
    }
});

test('relay signs exact response bytes including empty pulls and rejects nonce replay', function () {
    $f = relayFixture();
    try {
        $query = ['channel' => 'comments', 'account_ref' => 'actstationcontinent'];
        $response = relayCall($this, $f, 'office', 'pull', $query)->assertNoContent();
        $canonical = implode("\n", ['office-response-v1', '204', $response->headers->get('X-Office-Request-Nonce'),
            $response->headers->get('X-Office-Timestamp'), $response->headers->get('X-Office-Nonce'), hash('sha256', $response->getContent())]);
        expect(hash_equals(hash_hmac('sha256', $canonical, $f['principals']['office']['secret']), $response->headers->get('X-Office-Signature')))->toBeTrue();
        $nonce = $response->headers->get('X-Office-Request-Nonce');
        $target = '/api/v1/internal/agent-office/social-relay/pull';
        $body = json_encode($query, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $identity = $f['principals']['office'];
        $timestamp = (string) time();
        $idempotency = (string) Str::uuid();
        relayCall($this, $f, 'office', 'pull', $query, [
            'X-Agent-Nonce' => $nonce, 'X-Agent-Timestamp' => $timestamp, 'Idempotency-Key' => $idempotency,
            'X-Agent-Signature' => app(AgentOfficeSecurity::class)->signature('POST', $target, $timestamp, $nonce, 'office', $identity['token'], $body, $idempotency, $identity['secret']),
        ])->assertConflict()->assertJsonPath('meta.code', 'gateway_replay');
    } finally {
        relayCleanup($f);
    }
});

test('relay rejects expired oversized and contradictory dispatch and receipt payloads', function () {
    $f = relayFixture();
    try {
        $body = relayDispatch();
        $payload = $body['payload'];
        $bad = $body;
        $bad['payload']['dm_text'] = 'not allowed';
        relayCall($this, $f, 'office', 'publish', $bad)->assertUnprocessable();
        $bad = $body;
        $bad['payload']['reply_text'] = str_repeat('é', 4001);
        relayCall($this, $f, 'office', 'publish', $bad)->assertUnprocessable();
        foreach ([['not_before' => now()->addDays(2)->toIso8601String()], ['not_before' => now()->subDays(2)->toIso8601String()], ['expires_at' => now()->addMinute()->toIso8601String()]] as $invalid) {
            relayCall($this, $f, 'office', 'publish', array_replace($body, $invalid))->assertUnprocessable();
        }
        $bad = $body;
        $bad['payload']['lease_expires_at'] = now()->addMinute()->toIso8601String();
        relayCall($this, $f, 'office', 'publish', $bad)->assertUnprocessable();
        $receipt = ['channel' => 'receipts', 'account_ref' => 'actstationcontinent', 'delivery_id' => (string) Str::uuid(),
            'payload' => ['account_ref' => 'actstationcontinent', 'item_id' => $payload['item_id'], 'lease_token' => $payload['lease_token'],
                'reply' => ['state' => 'failed'], 'dm' => ['state' => 'sent', 'receipt_present' => true]]];
        relayCall($this, $f, 'sender', 'publish', $receipt)->assertUnprocessable();
        $receipt['payload']['reply'] = ['state' => 'sent', 'id' => '789'];
        $receipt['payload']['dm']['receipt_present'] = 1;
        relayCall($this, $f, 'sender', 'publish', $receipt)->assertUnprocessable();
        $receipt['payload']['dm']['receipt_present'] = true;
        relayCall($this, $f, 'sender', 'publish', $receipt)->assertCreated();
        expect(DB::table('social_relay_messages')->count())->toBe(1);
    } finally {
        relayCleanup($f);
    }
});

test('relay pull holds comments until durable ack and allows safe redelivery only', function () {
    $f = relayFixture();
    try {
        $id = (string) Str::uuid();
        relayCall($this, $f, 'collector', 'publish', ['channel' => 'comments', 'account_ref' => 'actstationcontinent', 'delivery_id' => $id, 'payload' => relayComment()])->assertCreated();
        $query = ['channel' => 'comments', 'account_ref' => 'actstationcontinent'];
        $first = relayCall($this, $f, 'office', 'pull', $query)->assertOk()->json('data');
        relayCall($this, $f, 'office', 'pull', $query)->assertNoContent();
        DB::table('social_relay_messages')->where('delivery_id', $id)->update(['claim_expires_at' => now()->subSecond()]);
        $second = relayCall($this, $f, 'office', 'pull', $query)->assertOk()->json('data');
        expect($first['claim_token'])->not->toBe($second['claim_token']);
        relayCall($this, $f, 'office', 'ack', $query + ['delivery_id' => $id, 'claim_token' => $first['claim_token']])->assertConflict();
        $ack = $query + ['delivery_id' => $id, 'claim_token' => $second['claim_token']];
        relayCall($this, $f, 'office', 'ack', $ack)->assertOk();
        relayCall($this, $f, 'office', 'ack', $ack)->assertOk();
        expect(DB::table('social_relay_messages')->where('delivery_id', $id)->value('payload'))->toBeNull();
        relayCall($this, $f, 'office', 'pull', $query)->assertNoContent();
    } finally {
        relayCleanup($f);
    }
});

test('relay signed publish persists encrypted comments and deduplicates before office is online', function () {
    $f = relayFixture();
    try {
        $payload = ['channel' => 'comments', 'account_ref' => 'actstationcontinent', 'delivery_id' => (string) Str::uuid(), 'payload' => relayComment()];
        $response = relayCall($this, $f, 'collector', 'publish', $payload)->assertCreated()->assertJsonPath('data.status', 'pending');
        expect($response->headers->has('X-Office-Signature'))->toBeTrue();
        relayCall($this, $f, 'collector', 'publish', $payload)->assertOk()->assertJsonPath('data.duplicate', true);
        expect(DB::table('social_relay_messages')->count())->toBe(1);
        expect(DB::table('social_relay_messages')->value('payload'))->not->toContain('synthetic-comment-canary');
        $payload['payload']['comments'][0]['text'] = 'different';
        relayCall($this, $f, 'collector', 'publish', $payload)->assertConflict();
        Http::assertNothingSent();
    } finally {
        relayCleanup($f);
    }
});

test('internal monitor lists the comment journey with filters and search only for email operators', function () {
    $f = relayFixture();
    try {
        $id = (string) Str::uuid();
        $comment = relayComment();
        $comment['comments'][0]['username'] = 'warga_satu';
        relayCall($this, $f, 'collector', 'publish', ['channel' => 'comments', 'account_ref' => 'actstationcontinent', 'delivery_id' => $id, 'payload' => $comment])->assertCreated();
        relayCall($this, $f, 'office', 'publish', relayDispatch('456', ['not_before' => now()->addMinutes(5)->toIso8601String()]))->assertCreated();
        $permission = Permission::firstOrCreate(['name' => 'setting.email', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'relay-monitor-'.bin2hex(random_bytes(4)), 'guard_name' => 'web', 'panel_access' => 'internal']);
        $role->givePermissionTo($permission);
        $operator = User::withoutEvents(fn () => User::factory()->create(['is_active' => true]));
        $operator->assignRole($role);
        $plain = Role::create(['name' => 'relay-plain-'.bin2hex(random_bytes(4)), 'guard_name' => 'web', 'panel_access' => 'internal']);
        $outsider = User::withoutEvents(fn () => User::factory()->create(['is_active' => true]));
        $outsider->assignRole($plain);
        $this->actingAs($outsider)->get('/admin/social-relay')->assertForbidden();
        $this->actingAs($operator)->get('/admin/social-relay')->assertOk()->assertSee('Monitoring Balas Komentar')
            ->assertSee('synthetic-comment-canary')->assertSee('Synthetic reply')->assertSee('@warga_satu')->assertSee('Terjadwal kirim')
            ->assertSee('@actstationcontinent')->assertSee('ke: balasan publik')->assertSee('Lihat perjalanan');
        $this->get('/admin/social-relay?search=@warga_satu&status=terjadwal&platform=instagram')->assertOk()->assertSee('synthetic-comment-canary');
        $this->get('/admin/social-relay?search=orang_lain')->assertOk()->assertDontSee('synthetic-comment-canary');
        $this->get('/admin/social-relay?status=terkirim')->assertOk()->assertDontSee('synthetic-comment-canary');
        $this->get('/admin/social-relay?status=hapus')->assertSessionHasErrors('status');
        $this->get('/admin/social-relay?from=2026-11-20&until=2026-11-01')->assertSessionHasErrors('until');
        Http::assertNothingSent();
    } finally {
        relayCleanup($f);
    }
});
