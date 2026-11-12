<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class SocialRelay
{
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

        return DB::transaction(function () use ($scope, $raw, $hash, $data, $principal): array {
            $inserted = DB::table('social_relay_messages')->insertOrIgnore($scope + [
                'producer_id' => $principal, 'payload_hash' => $hash, 'payload' => Crypt::encryptString($raw),
                'status' => 'pending', 'expires_at' => isset($data['expires_at']) ? Carbon::parse($data['expires_at'])->utc() : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $row = DB::table('social_relay_messages')->where($scope)->lockForUpdate()->first();
            abort_unless($row && $row->producer_id === $principal && hash_equals($row->payload_hash, $hash), 409, 'relay_conflict');
            $expiry = isset($data['expires_at']) ? Carbon::parse($data['expires_at'])->utc()->toDateTimeString() : null;
            abort_unless($row->expires_at === $expiry, 409, 'relay_expiry_conflict');
            if ($inserted) {
                $this->event($row->id, $principal, 'published');
            }

            return ['delivery_id' => $row->delivery_id, 'status' => $row->status, 'duplicate' => ! (bool) $inserted];
        }, 3);
    }

    public function pull(array $data, int $principal): ?array
    {
        return DB::transaction(function () use ($data, $principal): ?array {
            $query = fn () => DB::table('social_relay_messages')->where('channel', $data['channel'])->where('account_ref', $data['account_ref']);
            // Dispatch expiry is terminal: a lost claim response may already have caused a send.
            foreach ($query()->where('channel', 'dispatch')->whereIn('status', ['pending', 'claimed'])->where('expires_at', '<=', now())->lockForUpdate()->get() as $old) {
                DB::table('social_relay_messages')->where('id', $old->id)->update(['status' => 'unknown', 'updated_at' => now()]);
                $this->event($old->id, $principal, 'dispatch_expired');
            }
            $row = $query()->where(function ($q) use ($data): void {
                $q->where('status', 'pending');
                if ($data['channel'] !== 'dispatch') {
                    $q->orWhere(fn ($q) => $q->where('status', 'claimed')->where('claim_expires_at', '<=', now()));
                }
            })->orderBy('id')->lockForUpdate()->first();
            if (! $row) {
                return null;
            }
            $token = bin2hex(random_bytes(32));
            $expires = $row->channel === 'dispatch' ? Carbon::parse($row->expires_at) : now()->addSeconds(120);
            DB::table('social_relay_messages')->where('id', $row->id)->update([
                'status' => 'claimed', 'claim_hash' => hash('sha256', $token), 'claimed_by' => $principal,
                'claim_expires_at' => $expires, 'updated_at' => now(),
            ]);
            $this->event($row->id, $principal, 'claimed');

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
            }

            return ['delivery_id' => $row->delivery_id, 'status' => 'acknowledged'];
        }, 3);
    }

    private function event(int $message, int $principal, string $action): void
    {
        DB::table('social_relay_events')->insert(['message_id' => $message, 'principal_id' => $principal, 'action' => $action, 'created_at' => now()]);
    }
}
