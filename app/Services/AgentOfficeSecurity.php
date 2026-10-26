<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

class AgentOfficeSecurity
{
    public function snapshot(bool $requireActive = true): array
    {
        if ($requireActive && ! config('agent_office.enabled')) {
            throw new RuntimeException('gateway_disabled');
        }
        $path = self::privatePath('agent_office.registry_path');
        if ($requireActive && file_exists($path.'.disabled')) {
            throw new RuntimeException('emergency_disabled');
        }
        if ($requireActive && file_exists($path.'.pending')) {
            throw new RuntimeException('registry_change_pending');
        }
        if (! is_file($path) || filesize($path) > 262144) {
            throw new RuntimeException('registry_unavailable');
        }
        try {
            $data = json_decode($this->cipher()->decryptString(file_get_contents($path)), true, 32, JSON_THROW_ON_ERROR);
            $this->validate($data);
        } catch (Throwable) {
            throw new RuntimeException('registry_invalid');
        }
        if ($requireActive && (! $data['enabled'] || $data['expires_at'] <= time())) {
            throw new RuntimeException('registry_disabled');
        }

        return $data;
    }

    public function allowedIp(string $ip, array $snapshot): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        foreach ($snapshot['allowlist'] as $entry) {
            if (($entry['enabled'] ?? true) && ($entry['expires_at'] === null || $entry['expires_at'] > time()) && IpUtils::checkIp($ip, $entry['cidr'])) {
                return true;
            }
        }

        return false;
    }

    public function key(string $id, array $snapshot): array
    {
        $key = $snapshot['keys'][$id] ?? null;
        if (! is_array($key) || ! $key['enabled'] || $key['expires_at'] <= time()) {
            throw new RuntimeException('key_denied');
        }
        $key['secret'] = base64_decode($key['secret'], true);

        return $key;
    }

    public function signature(string $method, string $target, string $timestamp, string $nonce, string $keyId, string $token, string $body, string $idempotency, string $secret): string
    {
        return hash_hmac('sha256', implode("\n", ['v1', $method, $target, $timestamp, $nonce, $keyId, hash('sha256', $token), hash('sha256', $body), $idempotency]), $secret);
    }

    public function publish(array $snapshot, ?int $expectedVersion = null, ?array $audit = null): void
    {
        $this->validate($snapshot);
        $path = self::privatePath('agent_office.registry_path');
        $ciphertext = $this->cipher()->encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR));
        $lock = fopen($path.'.lock', 'c+b');
        if ($lock === false) {
            throw new RuntimeException('registry_lock_failed');
        }
        $temp = $path.'.'.bin2hex(random_bytes(8));
        $commitAttempted = false;
        $pending = false;
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('registry_lock_failed');
            }
            if (is_file($path)) {
                $current = $this->snapshot(false);
                if ($snapshot['version'] <= $current['version'] || ($expectedVersion !== null && $current['version'] !== $expectedVersion)) {
                    throw new RuntimeException('registry_version_conflict');
                }
            } elseif ($expectedVersion !== null) {
                throw new RuntimeException('registry_version_conflict');
            }
            if (file_exists($path.'.pending')) {
                throw new RuntimeException('registry_change_pending');
            }
            self::writePendingMarker($path.'.pending');
            $pending = true;
            if ($audit !== null) {
                $this->audit($audit + ['event' => 'allowlist_publish_intent', 'version' => $snapshot['version']]);
            }
            $f = fopen($temp, 'xb');
            if ($f === false) {
                throw new RuntimeException('registry_write_failed');
            }
            try {
                chmod($temp, 0600);
                if (fwrite($f, $ciphertext) !== strlen($ciphertext) || ! fflush($f) || ! fsync($f)) {
                    throw new RuntimeException('registry_write_failed');
                }
            } finally {
                fclose($f);
            }
            if (file_get_contents($temp) !== $ciphertext) {
                throw new RuntimeException('registry_publish_failed');
            }
            $commitAttempted = true;
            if (! rename($temp, $path) || file_get_contents($path) !== $ciphertext) {
                throw new RuntimeException('registry_publish_failed');
            }
            if ($audit !== null) {
                $this->audit($audit + ['event' => 'allowlist_applied', 'version' => $snapshot['version']]);
            }
            if (! unlink($path.'.pending')) {
                throw new RuntimeException('registry_pending_clear_failed');
            }
        } catch (Throwable $error) {
            if ($commitAttempted) {
                // Rename is not rollback. Deny traffic until explicit reconciliation.
                self::writeDisableMarker($path.'.disabled');
                throw new RuntimeException('registry_publish_unknown', previous: $error);
            }
            if ($pending && ! unlink($path.'.pending')) {
                throw new RuntimeException('registry_publish_unknown', previous: $error);
            }
            throw $error;
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function disable(): void
    {
        // Server filesystem authority only. Never decrypt registry or reset replay state.
        $path = self::privatePath('agent_office.registry_path').'.disabled';
        $lock = @fopen(substr($path, 0, -9).'.lock', 'c+b');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('registry_lock_failed');
        }
        try {
            self::writeDisableMarker($path);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function writePendingMarker(string $path): void
    {
        $f = @fopen($path, 'xb');
        if ($f === false) {
            throw new RuntimeException('registry_change_pending');
        }
        try {
            $line = json_encode(['event' => 'registry_change_pending', 'at' => gmdate('c')], JSON_THROW_ON_ERROR)."\n";
            if (! chmod($path, 0600) || fwrite($f, $line) !== strlen($line) || ! fflush($f) || ! fsync($f)) {
                throw new RuntimeException('registry_pending_failed');
            }
        } finally {
            fclose($f);
        }
    }

    private static function writeDisableMarker(string $path): void
    {
        $f = @fopen($path, 'xb');
        if ($f !== false) {
            try {
                $line = json_encode(['event' => 'emergency_disabled', 'at' => gmdate('c')], JSON_THROW_ON_ERROR)."\n";
                if (! chmod($path, 0600) || fwrite($f, $line) !== strlen($line) || ! fflush($f) || ! fsync($f)) {
                    throw new RuntimeException('disable_audit_failed');
                }
            } finally {
                fclose($f);
            }
        } elseif (! is_file($path) || ! str_contains((string) file_get_contents($path), 'emergency_disabled')) {
            throw new RuntimeException('disable_audit_failed');
        }
    }

    public function reenable(int $expectedVersion, int $operatorId): void
    {
        $path = self::privatePath('agent_office.registry_path');
        $f = fopen($path.'.lock', 'c+b');
        if ($f === false) {
            throw new RuntimeException('registry_lock_failed');
        }
        try {
            if (! flock($f, LOCK_EX)) {
                throw new RuntimeException('registry_lock_failed');
            }
            $data = $this->snapshot(false);
            if ($data['version'] !== $expectedVersion || ! $data['enabled'] || $data['expires_at'] <= time() || $operatorId < 1) {
                throw new RuntimeException('registry_version_conflict');
            }
            if (! is_file($path.'.disabled') && ! is_file($path.'.pending')) {
                throw new RuntimeException('reenable_failed');
            }
            if (! is_file($path.'.pending')) {
                self::writePendingMarker($path.'.pending');
            }
            $this->audit(['event' => 'emergency_reenable_intent', 'operator_id' => $operatorId, 'version' => $expectedVersion]);
            // Preserve emergency audit even after separately authorized re-enable.
            if (is_file($path.'.disabled') && (! rename($path.'.disabled', $path.'.disabled.'.bin2hex(random_bytes(8)).'.audit') || file_exists($path.'.disabled'))) {
                throw new RuntimeException('reenable_failed');
            }
            try {
                $this->audit(['event' => 'emergency_reenable', 'operator_id' => $operatorId, 'version' => $expectedVersion]);
                if (! unlink($path.'.pending')) {
                    throw new RuntimeException('registry_pending_clear_failed');
                }
            } catch (Throwable $error) {
                self::writeDisableMarker($path.'.disabled');
                throw $error;
            }
        } finally {
            flock($f, LOCK_UN);
            fclose($f);
        }
    }

    public function replaceAllowlist(array $entries, int $expectedVersion, int $operatorId): array
    {
        $snapshot = $this->snapshot(false);
        if ($snapshot['version'] !== $expectedVersion || $operatorId < 1) {
            throw new RuntimeException('registry_version_conflict');
        }
        $old = array_column($snapshot['allowlist'], null, 'id');
        foreach ($entries as &$entry) {
            $entry['created_by'] = $old[$entry['id'] ?? '']['created_by'] ?? $operatorId;
            $entry['version'] = $expectedVersion + 1;
        }
        unset($entry);
        $snapshot['version']++;
        $snapshot['allowlist'] = $entries;
        try {
            $this->publish($snapshot, $expectedVersion, ['operator_id' => $operatorId]);

            return ['status' => 'applied', 'effective_version' => $snapshot['version']];
        } catch (Throwable $e) {
            if ($e->getMessage() === 'registry_version_conflict') {
                throw $e;
            }
            try {
                $version = $this->snapshot(false)['version'];
            } catch (Throwable) {
                $version = null;
            }

            return ['status' => $e->getMessage() === 'registry_publish_unknown' || $version !== $expectedVersion ? 'unknown' : 'failed', 'effective_version' => $version];
        }
    }

    public function audit(array $entry): void
    {
        $path = self::privatePath('agent_office.audit_path');
        $line = json_encode(array_intersect_key($entry, array_flip(['event', 'operator_id', 'version'])) + ['at' => gmdate('c')], JSON_THROW_ON_ERROR)."\n";
        $f = @fopen($path, 'ab');
        if ($f === false) {
            throw new RuntimeException('audit_unavailable');
        }
        try {
            if (! flock($f, LOCK_EX) || fstat($f)['size'] >= 10 * 1024 * 1024 || fwrite($f, $line) !== strlen($line) || ! fflush($f) || ! fsync($f)) {
                throw new RuntimeException('audit_unavailable');
            }
        } finally {
            fclose($f);
        }
    }

    public static function privatePath(string $config): string
    {
        $path = config($config);
        if (! is_string($path) || ! preg_match('~^(?:[A-Za-z]:[/\\\\]|/)~', $path) || is_link($path) || ! is_dir(dirname($path))) {
            throw new RuntimeException('private_path_invalid');
        }
        $parent = strtolower(str_replace('\\', '/', realpath(dirname($path))));
        $public = strtolower(str_replace('\\', '/', realpath(public_path())));
        $real = strtolower(str_replace('\\', '/', realpath($path) ?: $parent.'/'.basename($path)));
        if ($parent === $public || str_starts_with($parent, $public.'/') || $real === $public || str_starts_with($real, $public.'/')) {
            throw new RuntimeException('private_path_invalid');
        }

        if (config('agent_office.private_acl_required', true)) {
            AgentOfficePrivateDirectory::verify(dirname($path));
        }

        return $path;
    }

    private function cipher(): Encrypter
    {
        $raw = config('agent_office.master_key');
        $key = is_string($raw) ? base64_decode($raw, true) : false;
        if (! is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('master_key_invalid');
        }
        $appKey = (string) config('app.key');
        $appBytes = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7), true) : $appKey;
        if (is_string($appBytes) && hash_equals($appBytes, $key)) {
            throw new RuntimeException('master_key_invalid');
        }

        return new Encrypter($key, 'AES-256-GCM');
    }

    public static function validCidr(string $cidr): bool
    {
        $parts = explode('/', $cidr);
        if (count($parts) !== 2 || ! ctype_digit($parts[1]) || (string) (int) $parts[1] !== $parts[1] || (int) $parts[1] < 1 || filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
            return false;
        }
        $bytes = inet_pton($parts[0]);
        $bits = (int) $parts[1];
        if ($bits > strlen($bytes) * 8 || inet_ntop($bytes) !== $parts[0]) {
            return false;
        }
        for ($i = 0; $i < strlen($bytes); $i++) {
            $keep = max(0, min(8, $bits - $i * 8));
            if ((ord($bytes[$i]) & (255 >> $keep)) !== 0) {
                return false;
            }
        }

        return true;
    }

    private function validate(mixed $data): void
    {
        if (! is_array($data) || ! is_int($data['version'] ?? null) || $data['version'] < 1 || ! is_bool($data['enabled'] ?? null) || ! is_int($data['expires_at'] ?? null) || ! is_array($data['allowlist'] ?? null) || ! array_is_list($data['allowlist']) || ! is_array($data['keys'] ?? null) || count($data['allowlist']) > 100 || count($data['keys']) > 100) {
            throw new RuntimeException('registry_invalid');
        }
        foreach ($data['allowlist'] as $entry) {
            if (! is_array($entry) || ! is_string($entry['cidr'] ?? null) || ! self::validCidr($entry['cidr']) || ! array_key_exists('expires_at', $entry) || ($entry['expires_at'] !== null && ! is_int($entry['expires_at']))) {
                throw new RuntimeException('registry_invalid');
            }
        }
        foreach ($data['keys'] as $id => $key) {
            if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id) || ! is_array($key) || ! is_string($key['secret'] ?? null) || strlen(base64_decode($key['secret'], true) ?: '') !== 32 || ! is_int($key['principal_id'] ?? null) || $key['principal_id'] < 1 || ! is_int($key['expires_at'] ?? null) || ! is_bool($key['enabled'] ?? null)) {
                throw new RuntimeException('registry_invalid');
            }
        }
    }
}
