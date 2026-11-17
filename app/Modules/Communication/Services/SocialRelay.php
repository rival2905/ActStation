<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class SocialRelay
{
    /** Minimum gap between two dispatch claims per account (one Instagram write per 45 s). */
    private const DISPATCH_GAP = 45;

    public function publish(array $data, int $principal): array
    {
        // Canonical objects ignore JSON key order; list order and text bytes remain exact.
        $sort = function (mixed $value) use (&$sort): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($sort, $value);
        };
        $raw = json_encode($sort($data['payload']), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', $raw);
        $scope = array_intersect_key($data, array_flip(['channel', 'account_ref', 'delivery_id']));
        $time = fn (string $key) => isset($data[$key]) ? Carbon::parse($data[$key])->utc()->toDateTimeString() : null;

        return DB::transaction(function () use ($scope, $raw, $hash, $data, $principal, $time): array {
            $inserted = DB::table('social_relay_messages')->insertOrIgnore($scope + [
                'producer_id' => $principal, 'payload_hash' => $hash, 'payload' => Crypt::encryptString($raw),
                'status' => 'pending', 'expires_at' => $time('expires_at'), 'not_before' => $time('not_before'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $row = DB::table('social_relay_messages')->where($scope)->lockForUpdate()->first();
            abort_unless($row && $row->producer_id === $principal && hash_equals($row->payload_hash, $hash), 409, 'relay_conflict');
            abort_unless($row->expires_at === $time('expires_at') && $row->not_before === $time('not_before'), 409, 'relay_expiry_conflict');
            if ($inserted) {
                $this->event($row->id, $principal, 'published');
                $this->trace($data['payload'], $row);
            }

            return ['delivery_id' => $row->delivery_id, 'status' => $row->status, 'duplicate' => ! (bool) $inserted];
        }, 3);
    }

    public function pull(array $data, int $principal): ?array
    {
        return DB::transaction(function () use ($data, $principal): ?array {
            $query = fn () => DB::table('social_relay_messages')->where('channel', $data['channel'])->where('account_ref', $data['account_ref']);
            if ($data['channel'] === 'dispatch') {
                // Dispatch is never redelivered: an expired dispatch or an unacknowledged claim may already have caused a send.
                $stale = $query()->where(fn ($q) => $q->where(fn ($q) => $q->whereIn('status', ['pending', 'claimed'])->where('expires_at', '<=', now()))
                    ->orWhere(fn ($q) => $q->where('status', 'claimed')->where('claim_expires_at', '<=', now())))->lockForUpdate()->get();
                foreach ($stale as $old) {
                    DB::table('social_relay_messages')->where('id', $old->id)->update(['status' => 'unknown', 'updated_at' => now()]);
                    $this->event($old->id, $principal, 'dispatch_expired');
                    $this->traceDispatch($old, ['status' => 'tidak_pasti', 'error_code' => 'claim_expired']);
                }
                $last = DB::table('social_relay_events as e')->join('social_relay_messages as m', 'm.id', '=', 'e.message_id')
                    ->where('m.channel', 'dispatch')->where('m.account_ref', $data['account_ref'])->where('e.action', 'claimed')->max('e.created_at');
                if ($last !== null && Carbon::parse($last)->addSeconds(self::DISPATCH_GAP)->isFuture()) {
                    return null;
                }
            }
            $row = $query()->where(function ($q) use ($data): void {
                $q->where('status', 'pending');
                if ($data['channel'] !== 'dispatch') {
                    $q->orWhere(fn ($q) => $q->where('status', 'claimed')->where('claim_expires_at', '<=', now()));
                }
            })->where(fn ($q) => $q->whereNull('not_before')->orWhere('not_before', '<=', now()))
                ->orderByRaw('coalesce(not_before, created_at)')->orderBy('id')->lockForUpdate()->first();
            if (! $row) {
                return null;
            }
            $token = bin2hex(random_bytes(32));
            // A scheduled dispatch has no expiry while waiting; once claimed it gets a 120 s lease and is never redelivered.
            $expires = $row->channel === 'dispatch' && $row->expires_at !== null ? Carbon::parse($row->expires_at) : now()->addSeconds(120);
            DB::table('social_relay_messages')->where('id', $row->id)->update([
                'status' => 'claimed', 'claim_hash' => hash('sha256', $token), 'claimed_by' => $principal,
                'claim_expires_at' => $expires, 'updated_at' => now(),
            ]);
            $this->event($row->id, $principal, 'claimed');
            if ($row->channel === 'dispatch') {
                $this->traceDispatch($row, ['status' => 'dieksekusi', 'claimed_at' => now()]);
            }

            return ['delivery_id' => $row->delivery_id, 'channel' => $row->channel, 'account_ref' => $row->account_ref,
                'claim_token' => $token, 'claim_expires_at' => $expires->toIso8601String(),
                'payload' => json_decode(Crypt::decryptString($row->payload), true, 32, JSON_THROW_ON_ERROR)];
        }, 3);
    }

    public function ack(array $data, int $principal): array
    {
        return DB::transaction(function () use ($data, $principal): array {
            $row = DB::table('social_relay_messages')->where(array_intersect_key($data, array_flip(['channel', 'account_ref', 'delivery_id'])))->lockForUpdate()->first();
            abort_unless($row && $row->claimed_by === $principal && is_string($row->claim_hash) && hash_equals($row->claim_hash, hash('sha256', $data['claim_token'])), 409, 'relay_claim_invalid');
            if ($row->status !== 'acknowledged') {
                abort_unless($row->status === 'claimed' && Carbon::parse($row->claim_expires_at)->isFuture(), 409, 'relay_claim_expired');
                DB::table('social_relay_messages')->where('id', $row->id)->update(['status' => 'acknowledged', 'payload' => null, 'acknowledged_at' => now(), 'updated_at' => now()]);
                $this->event($row->id, $principal, 'acknowledged');
                $traces = fn () => DB::table('social_relay_traces')->where('account_ref', $row->account_ref);
                if ($row->channel === 'comments') {
                    $traces()->where('comment_delivery_id', $row->delivery_id)->whereNull('office_received_at')->update(['office_received_at' => now(), 'updated_at' => now()]);
                    $traces()->where('comment_delivery_id', $row->delivery_id)->where('status', 'masuk')->update(['status' => 'di_office']);
                } elseif ($row->channel === 'receipts') {
                    $traces()->where('receipt_delivery_id', $row->delivery_id)->whereNull('confirmed_at')->update(['confirmed_at' => now(), 'updated_at' => now()]);
                }
            }

            return ['delivery_id' => $row->delivery_id, 'status' => 'acknowledged'];
        }, 3);
    }

    /** Monitoring trace per comment for the internal table. Texts encrypted; never consulted for relay authority. */
    private function trace(array $payload, object $row): void
    {
        $now = now();
        if ($row->channel === 'comments') {
            foreach ($payload['comments'] as $comment) {
                DB::table('social_relay_traces')->insertOrIgnore(['account_ref' => $row->account_ref, 'platform' => $payload['platform'],
                    'comment_id' => $comment['id'], 'media_id' => $payload['media']['id'], 'permalink' => $payload['media']['permalink'],
                    'username' => $comment['username'] ?? null, 'comment_text' => Crypt::encryptString($comment['text']),
                    'commented_at' => Carbon::parse($comment['timestamp'])->utc(), 'status' => 'masuk', 'received_at' => $now,
                    'comment_delivery_id' => $row->delivery_id, 'created_at' => $now, 'updated_at' => $now]);
            }
        } elseif ($row->channel === 'dispatch') {
            $values = ['item_id' => $payload['item_id'], 'dispatch_delivery_id' => $row->delivery_id, 'lane' => $payload['lane'],
                'reply_text' => Crypt::encryptString($payload['reply_text']), 'dm_text' => $payload['dm_text'] === null ? null : Crypt::encryptString($payload['dm_text']),
                'scheduled_for' => $row->not_before ?? $now, 'dispatched_at' => $now, 'status' => 'terjadwal', 'updated_at' => $now];
            if (DB::table('social_relay_traces')->where('account_ref', $row->account_ref)->where('comment_id', $payload['comment_id'])->update($values) === 0) {
                // Comment collected before tracing existed: keep the reply visible anyway.
                DB::table('social_relay_traces')->insertOrIgnore($values + ['account_ref' => $row->account_ref, 'platform' => $payload['platform'],
                    'comment_id' => $payload['comment_id'], 'media_id' => $payload['media_id'], 'created_at' => $now]);
            }
        } elseif ($row->channel === 'receipts') {
            [$reply, $dm] = [$payload['reply']['state'], $payload['dm']['state']];
            $status = in_array('unknown', [$reply, $dm], true) ? 'tidak_pasti' : ($reply === 'sent' && in_array($dm, ['sent', 'skipped'], true) ? 'terkirim' : 'gagal');
            DB::table('social_relay_traces')->where('account_ref', $row->account_ref)->where('item_id', $payload['item_id'])->update([
                'status' => $status, 'reply_state' => $reply, 'dm_state' => $dm, 'reply_id' => $payload['reply']['id'] ?? null,
                'error_code' => $payload['error_code'] ?? null, 'result_at' => $now, 'receipt_delivery_id' => $row->delivery_id, 'updated_at' => $now]);
        }
    }

    private function traceDispatch(object $row, array $values): void
    {
        DB::table('social_relay_traces')->where('account_ref', $row->account_ref)->where('dispatch_delivery_id', $row->delivery_id)
            ->whereNotIn('status', ['terkirim', 'gagal'])->update($values + ['updated_at' => now()]);
    }

    private function event(int $message, int $principal, string $action): void
    {
        DB::table('social_relay_events')->insert(['message_id' => $message, 'principal_id' => $principal, 'action' => $action, 'created_at' => now()]);
    }
}
