<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AgentOfficePrincipal;
use App\Services\AgentOfficePeer;
use App\Services\AgentOfficeSecurity;
use App\Services\AgentOfficeState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AgentOfficeGateway
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) Str::uuid();
        $request->attributes->set('gateway_request_id', $id);
        $principalId = null;
        $keyId = null;
        $status = 403;
        $code = 'gateway_forbidden';
        try {
            if (! config('agent_office.enabled')) {
                return $this->failure($id, $status, $code);
            }
            $security = app(AgentOfficeSecurity::class);
            $state = app(AgentOfficeState::class);
            $snapshot = $security->snapshot();
            try {
                $resolved = AgentOfficePeer::resolve($request, config('agent_office.trusted_proxies', []));
            } catch (\RuntimeException) {
                return $this->failure($id, $status, $code);
            }
            $peer = $resolved['ip'];
            if (! $security->allowedIp($peer, $snapshot) || (! $resolved['secure'] && ! (config('agent_office.allow_loopback_http') && app()->environment(['local', 'testing']) && in_array($peer, ['127.0.0.1', '::1'], true)))) {
                return $this->failure($id, $status, $code);
            }
            if (! $state->hit('ip:'.$peer, 120, 60)) {
                return $this->failure($id, 429, 'gateway_rate_limited');
            }
            $target = (string) $request->server('REQUEST_URI');
            $lengths = $request->headers->all('Content-Length');
            $headerBytes = json_encode($request->headers->all());
            if (strlen((string) $request->server('QUERY_STRING')) > 1024 || $headerBytes === false || strlen($headerBytes) > 8192 || ($lengths !== [] && $lengths !== ['0'])) {
                return $this->failure($id, $status, $code);
            }
            if ($request->method() !== 'GET' || $request->getContent() !== '' || $request->headers->has('Idempotency-Key') || $request->headers->has('Transfer-Encoding') || strlen($target) > 2048 || $target !== $request->getRequestUri() || ! preg_match('~^/api/v1/internal/agent-office/system-health(?:\?[^#\s]*)?$~D', $target)) {
                return $this->failure($id, $status, $code);
            }
            $values = [];
            foreach (['Authorization', 'X-Agent-Key-Id', 'X-Agent-Timestamp', 'X-Agent-Nonce', 'X-Agent-Signature', 'Accept'] as $header) {
                $all = $request->headers->all($header);
                if (count($all) !== 1 || ! is_string($all[0]) || strlen($all[0]) > 512 || preg_match('/[\r\n,]/', $all[0]) || trim($all[0]) !== $all[0]) {
                    return $this->failure($id, $status, $code);
                }
                $values[$header] = $all[0];
            }
            if ($values['Accept'] !== 'application/json' || ! preg_match('/^Bearer ([1-9][0-9]*\|[A-Za-z0-9]{40,128})$/D', $values['Authorization'], $match) || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $values['X-Agent-Key-Id']) || ! preg_match('/^[0-9]{10}$/D', $values['X-Agent-Timestamp']) || abs(time() - (int) $values['X-Agent-Timestamp']) > 60 || ! preg_match('/^[a-f0-9]{64}$/D', $values['X-Agent-Nonce']) || ! preg_match('/^[a-f0-9]{64}$/D', $values['X-Agent-Signature'])) {
                return $this->failure($id, $status, $code);
            }
            $token = $match[1];
            $keyId = $values['X-Agent-Key-Id'];
            try {
                $key = $security->key($keyId, $snapshot);
            } catch (\RuntimeException) {
                return $this->failure($id, $status, $code);
            }
            $expected = $security->signature('GET', $target, $values['X-Agent-Timestamp'], $values['X-Agent-Nonce'], $keyId, $token, '', '', $key['secret']);
            if (! hash_equals($expected, $values['X-Agent-Signature'])) {
                return $this->failure($id, $status, $code);
            }
            if (! $state->claim('nonce:'.$keyId.':'.$values['X-Agent-Nonce'], 300)) {
                return $this->failure($id, 409, 'gateway_replay');
            }
            if ($request->getQueryString() !== null) {
                return $this->failure($id, 422, 'gateway_query_invalid');
            }
            $accessToken = PersonalAccessToken::findToken($token);
            if (! $accessToken || ! $accessToken->expires_at || $accessToken->expires_at->isPast() || ! $accessToken->tokenable instanceof AgentOfficePrincipal) {
                return $this->failure($id, 401, 'gateway_unauthenticated');
            }
            $principal = $accessToken->tokenable;
            $principalId = $principal->getKey();
            if (! $principal->enabled || $principalId !== $key['principal_id'] || ! is_array($accessToken->abilities) || in_array('*', $accessToken->abilities, true) || ! in_array('agent:health-read', $accessToken->abilities, true)) {
                return $this->failure($id, $status, $code);
            }
            if (! $state->hit('principal:'.$principalId, 60, 60)) {
                return $this->failure($id, 429, 'gateway_rate_limited');
            }
            $request->setUserResolver(fn () => $principal->withAccessToken($accessToken));
            $response = $next($request);
            $status = $response->getStatusCode();
            $code = $status === 200 ? 'gateway_ok' : 'gateway_unavailable';
            if (! $this->audit(['request_id' => $id, 'principal_id' => $principalId, 'key_id' => $keyId, 'action' => 'system-health', 'status' => $status, 'code' => $code, 'at' => gmdate('c')])) {
                return $this->failure($id, 503, 'gateway_unavailable');
            }

            return $response->header('Cache-Control', 'no-store, private')->header('X-Request-Id', $id);
        } catch (Throwable) {
            $status = 503;
            $code = 'gateway_unavailable';

            return $this->failure($id, $status, $code);
        }
    }

    private function failure(string $id, int $status, string $code): Response
    {
        // Bounded non-DB security log: no raw request values, headers or exception text.
        try {
            $this->audit(['request_id' => $id, 'action' => 'system-health', 'status' => $status, 'code' => $code, 'at' => gmdate('c')]);
        } catch (Throwable) { /* Denial remains denial when logging is unavailable. */
        }

        return response()->json(['message' => 'Gateway request denied.', 'errors' => (object) [], 'meta' => ['request_id' => $id, 'code' => $code]], $status)
            ->header('Cache-Control', 'no-store, private')->header('X-Request-Id', $id)
            ->withHeaders($status === 429 ? ['Retry-After' => '60'] : []);
    }

    private function audit(array $entry): bool
    {
        $path = config('agent_office.audit_path');
        if (! is_string($path) || ! is_dir(dirname($path)) || is_link($path)) {
            return false;
        }
        $real = strtolower(str_replace('\\', '/', realpath($path) ?: realpath(dirname($path))));
        $public = strtolower(str_replace('\\', '/', realpath(public_path())));
        if ($real === $public || str_starts_with($real, $public.'/')) {
            return false;
        }
        $f = @fopen($path, 'ab');
        if ($f === false) {
            return false;
        }
        try {
            if (flock($f, LOCK_EX)) {
                $line = json_encode($entry, JSON_THROW_ON_ERROR)."\n";
                if (fstat($f)['size'] >= 10 * 1024 * 1024) {
                    return false;
                }

                return fwrite($f, $line) === strlen($line) && fflush($f);
            }

            return false;
        } finally {
            fclose($f);
        }
    }
}
