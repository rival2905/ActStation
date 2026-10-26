<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

class AgentOfficeAdminAuth
{
    public function enroll(int $userId, string $outputPath): void
    {
        config(['agent_office.enrollment_output' => $outputPath]);
        AgentOfficeSecurity::privatePath('agent_office.enrollment_output');
        if ($userId < 1 || file_exists($outputPath)) {
            throw new RuntimeException('mfa_enrollment_denied');
        }
        $this->locked(function (array &$rows) use ($userId, $outputPath): void {
            if (isset($rows[$userId])) {
                throw new RuntimeException('mfa_already_enrolled');
            }
            $google = new Google2FA;
            $secret = $google->generateSecretKey();
            $f = @fopen($outputPath, 'xb');
            if ($f === false) {
                throw new RuntimeException('mfa_output_denied');
            }
            try {
                chmod($outputPath, 0600);
                $json = json_encode(['secret' => $secret, 'uri' => $google->getQRCodeUrl('ActStation', 'operator-'.$userId, $secret)], JSON_THROW_ON_ERROR);
                if (fwrite($f, $json) !== strlen($json) || ! fflush($f) || ! fsync($f)) {
                    throw new RuntimeException('mfa_output_failed');
                }
            } finally {
                fclose($f);
            }
            $rows[$userId] = ['secret' => $secret, 'last' => 0, 'failures' => 0, 'until' => 0];
        });
    }

    public function isEnrolled(int $userId): bool
    {
        return $this->locked(fn (array &$rows): bool => isset($rows[$userId]));
    }

    public function verify(int $userId, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $otp, #[\SensitiveParameter] string $passwordHash): void
    {
        $valid = $this->locked(function (array &$rows) use ($userId, $password, $otp, $passwordHash): bool {
            if (! isset($rows[$userId])) {
                return false;
            }
            $row = &$rows[$userId];
            if ($row['until'] > time()) {
                return false;
            }
            if ($row['until'] !== 0) {
                $row['failures'] = 0;
                $row['until'] = 0;
            }
            $step = false;
            if (preg_match('/^[0-9]{6}$/D', $otp) && Hash::check($password, $passwordHash)) {
                $step = (new Google2FA)->verifyKeyNewer($row['secret'], $otp, $row['last'], 1);
            }
            if ($step === false) {
                $row['failures']++;
                if ($row['failures'] >= 5) {
                    $row['until'] = time() + 300;
                }

                return false;
            }
            $row['last'] = $step;
            $row['failures'] = 0;
            $row['until'] = 0;

            return true;
        });
        if (! $valid) {
            throw new RuntimeException('mfa_verification_denied');
        }
    }

    private function locked(\Closure $callback): mixed
    {
        $path = AgentOfficeSecurity::privatePath('agent_office.admin_mfa_path');
        $key = base64_decode((string) config('agent_office.master_key'), true);
        $app = (string) config('app.key');
        $app = str_starts_with($app, 'base64:') ? base64_decode(substr($app, 7), true) : $app;
        if (! is_string($key) || strlen($key) !== 32 || $key === $app) {
            throw new RuntimeException('mfa_key_denied');
        }
        $cipher = new Encrypter($key, 'AES-256-GCM');
        $lock = @fopen($path.'.lock', 'c+b');
        if ($lock === false) {
            throw new RuntimeException('mfa_store_unavailable');
        }
        $temp = $path.'.'.bin2hex(random_bytes(8));
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('mfa_store_unavailable');
            }
            if (is_file($path) && filesize($path) > 262144) {
                throw new RuntimeException('mfa_store_invalid');
            }
            $rows = is_file($path) ? json_decode($cipher->decryptString(file_get_contents($path)), true, 32, JSON_THROW_ON_ERROR) : [];
            if (! is_array($rows) || count($rows) > 100) {
                throw new RuntimeException('mfa_store_invalid');
            }
            foreach ($rows as $row) {
                if (! is_array($row) || ! is_string($row['secret'] ?? null) || ! is_int($row['last'] ?? null) || ! is_int($row['failures'] ?? null) || ! is_int($row['until'] ?? null)) {
                    throw new RuntimeException('mfa_store_invalid');
                }
            }
            $before = $rows;
            $result = $callback($rows);
            if ($rows === $before && is_file($path)) {
                return $result;
            }
            $encrypted = $cipher->encryptString(json_encode($rows, JSON_THROW_ON_ERROR));
            $f = fopen($temp, 'xb');
            try {
                chmod($temp, 0600);
                if (fwrite($f, $encrypted) !== strlen($encrypted) || ! fflush($f) || ! fsync($f)) {
                    throw new RuntimeException('mfa_store_write_failed');
                }
            } finally {
                fclose($f);
            }
            if (! rename($temp, $path) || file_get_contents($path) !== $encrypted) {
                throw new RuntimeException('mfa_store_write_failed');
            }

            return $result;
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
