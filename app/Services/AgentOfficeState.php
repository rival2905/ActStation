<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class AgentOfficeState
{
    public function claim(string $key, int $ttl): bool
    {
        return $this->update('nonce:'.$key, 1, $ttl);
    }

    public function hit(string $key, int $limit, int $seconds): bool
    {
        return $this->update('quota:'.$key, $limit, $seconds);
    }

    private function update(string $key, int $limit, int $ttl): bool
    {
        if ($limit < 1 || $ttl < 1 || $ttl > 3600) {
            throw new RuntimeException('state_input_invalid');
        }
        $path = AgentOfficeSecurity::privatePath('agent_office.state_path');
        $f = fopen($path.'.lock', 'c+b');
        if ($f === false) {
            throw new RuntimeException('state_unavailable');
        }
        try {
            chmod($path.'.lock', 0600);
            if (! flock($f, LOCK_EX)) {
                throw new RuntimeException('state_lock_failed');
            }
            if (is_file($path) && filesize($path) > 2 * 1024 * 1024) {
                throw new RuntimeException('state_full');
            }
            $raw = is_file($path) ? file_get_contents($path) : null;
            $rows = $raw === null ? [] : json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($rows)) {
                throw new RuntimeException('state_invalid');
            }
            $now = time();
            foreach ($rows as $id => $row) {
                if (! is_string($id) || ! preg_match('/^[a-f0-9]{64}$/D', $id) || ! is_array($row) || ! is_int($row['expires'] ?? null) || ! is_int($row['count'] ?? null)) {
                    throw new RuntimeException('state_invalid');
                }
                if ($row['expires'] <= $now) {
                    unset($rows[$id]);
                }
            }
            $id = hash('sha256', $key);
            $row = $rows[$id] ?? ['expires' => $now + $ttl, 'count' => 0];
            if ($row['count'] >= $limit) {
                return false;
            }
            $row['count']++;
            $rows[$id] = $row;
            if (count($rows) > 10000) {
                throw new RuntimeException('state_full');
            }
            $json = json_encode($rows, JSON_THROW_ON_ERROR);
            $temp = $path.'.'.bin2hex(random_bytes(8));
            $out = fopen($temp, 'xb');
            if ($out === false) {
                throw new RuntimeException('state_write_failed');
            }
            try {
                chmod($temp, 0600);
                if (fwrite($out, $json) !== strlen($json) || ! fflush($out) || ! fsync($out)) {
                    throw new RuntimeException('state_write_failed');
                }
            } finally {
                fclose($out);
            }
            try {
                if (! rename($temp, $path)) {
                    throw new RuntimeException('state_write_failed');
                }
            } finally {
                if (is_file($temp)) {
                    unlink($temp);
                }
            }

            return true;
        } finally {
            flock($f, LOCK_UN);
            fclose($f);
        }
    }
}
