<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AgentOfficePrincipal;
use App\Services\AgentOfficeSecurity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('agent-office:provision {--name=} {--ability=agent:health-read} {--cidr=*} {--output=} {--days=30} {--disable}')]
#[Description('Provision or disable Agent Office machine credentials and security registry')]
class AgentOfficeProvision extends Command
{
    // One ability per token; the gateway rejects relay tokens holding anything else.
    private const ABILITIES = ['agent:health-read', 'agent:social-collector', 'agent:social-office', 'agent:social-sender'];

    protected $signature = 'agent-office:provision {--name=} {--ability=agent:health-read} {--cidr=*} {--output=} {--days=30} {--disable}';

    protected $description = 'Provision or disable Agent Office machine credentials and security registry';

    public function __construct(protected ?AgentOfficeSecurity $security = null)
    {
        parent::__construct();
    }

    public function handle(?AgentOfficeSecurity $security = null): int
    {
        $security = $this->security ?? $security ?? app(AgentOfficeSecurity::class);

        if ($this->option('disable')) {
            try {
                $security->disable();
                $this->info('Agent Office gateway disabled successfully.');

                return self::SUCCESS;
            } catch (Throwable) {
                $this->error('Failed to disable Agent Office gateway.');

                return self::FAILURE;
            }
        }

        $name = $this->option('name');
        if (! is_string($name) || trim($name) === '' || strlen($name) > 100) {
            $this->error('Invalid principal name.');

            return self::FAILURE;
        }
        $name = trim($name);

        $ability = $this->option('ability');
        if (! is_string($ability) || ! in_array($ability, self::ABILITIES, true)) {
            $this->error('Invalid ability.');

            return self::FAILURE;
        }

        $daysOpt = $this->option('days');
        if (! is_numeric($daysOpt) || (string) (int) $daysOpt !== (string) $daysOpt) {
            $this->error('Invalid days parameter.');

            return self::FAILURE;
        }
        $days = (int) $daysOpt;
        if ($days < 1 || $days > 30) {
            $this->error('Invalid days parameter.');

            return self::FAILURE;
        }

        $rawCidrs = (array) $this->option('cidr');
        if (empty($rawCidrs)) {
            $this->error('Invalid CIDR block.');

            return self::FAILURE;
        }

        $validatedCidrs = [];
        foreach ($rawCidrs as $rawCidr) {
            if (! is_string($rawCidr)) {
                $this->error('Invalid CIDR block.');

                return self::FAILURE;
            }
            $canonical = $this->validateAndCanonicalizeCidr(trim($rawCidr));
            if ($canonical === null) {
                $this->error('Invalid CIDR block.');

                return self::FAILURE;
            }
            if (! in_array($canonical, $validatedCidrs, true)) {
                $validatedCidrs[] = $canonical;
            }
        }

        $output = $this->option('output');
        if (! is_string($output) || trim($output) === '') {
            $this->error('Invalid output file path.');

            return self::FAILURE;
        }
        $output = trim($output);

        if (! preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $output)) {
            $this->error('Invalid output file path.');

            return self::FAILURE;
        }

        $normOutput = str_replace('\\', '/', $output);
        if (str_contains($normOutput, '/../') || str_ends_with($normOutput, '/..')) {
            $this->error('Invalid output file path.');

            return self::FAILURE;
        }

        if (file_exists($output) || is_link($output)) {
            $this->error('Invalid output file path.');

            return self::FAILURE;
        }

        $outputDir = dirname($output);
        if (! is_dir($outputDir) || is_link($outputDir)) {
            $this->error('Invalid output file path.');

            return self::FAILURE;
        }

        $realOutputDir = realpath($outputDir);
        if ($realOutputDir === false) {
            $this->error('Invalid output file path.');

            return self::FAILURE;
        }

        $publicPath = realpath(public_path());
        if ($publicPath !== false) {
            $normPublic = strtolower(str_replace('\\', '/', $publicPath));
            $normOutputDir = strtolower(str_replace('\\', '/', $realOutputDir));
            if ($normOutputDir === $normPublic || str_starts_with($normOutputDir, $normPublic.'/')) {
                $this->error('Invalid output file path.');

                return self::FAILURE;
            }
        }

        $masterKey = config('agent_office.master_key');
        if (! is_string($masterKey) || trim($masterKey) === '') {
            $this->error('Invalid master key configuration.');

            return self::FAILURE;
        }

        $decodedMaster = base64_decode($masterKey, true);
        if ($decodedMaster === false || strlen($decodedMaster) !== 32) {
            $this->error('Invalid master key configuration.');

            return self::FAILURE;
        }

        if (base64_encode($decodedMaster) !== $masterKey) {
            $this->error('Invalid master key configuration.');

            return self::FAILURE;
        }

        $appKey = (string) config('app.key');
        if ($appKey !== '') {
            if ($masterKey === $appKey) {
                $this->error('Invalid master key configuration.');

                return self::FAILURE;
            }
            $decodedApp = str_starts_with($appKey, 'base64:')
                ? base64_decode(substr($appKey, 7), true)
                : $appKey;
            if ($decodedApp !== false && hash_equals($decodedApp, $decodedMaster)) {
                $this->error('Invalid master key configuration.');

                return self::FAILURE;
            }
        }

        $registryPath = config('agent_office.registry_path');
        if (! $this->validateSecurityPath($registryPath)) {
            $this->error('Invalid security path configuration.');

            return self::FAILURE;
        }

        $statePath = config('agent_office.state_path');
        if (is_string($statePath) && ! $this->validateSecurityPath($statePath)) {
            $this->error('Invalid security path configuration.');

            return self::FAILURE;
        }

        $auditPath = config('agent_office.audit_path');
        if (is_string($auditPath) && ! $this->validateSecurityPath($auditPath)) {
            $this->error('Invalid security path configuration.');

            return self::FAILURE;
        }

        $existingSnapshot = null;
        if (file_exists($registryPath)) {
            $prevEnabled = config('agent_office.enabled');
            config(['agent_office.enabled' => true]);
            try {
                $existingSnapshot = $security->snapshot(false);
            } catch (Throwable) {
                $this->error('Failed to read existing Agent Office registry.');

                return self::FAILURE;
            } finally {
                config(['agent_office.enabled' => $prevEnabled]);
            }

            if (! is_array($existingSnapshot)
                || ! isset($existingSnapshot['version'])
                || ! is_array($existingSnapshot['keys'] ?? null)
                || ! is_array($existingSnapshot['allowlist'] ?? null)
            ) {
                $this->error('Failed to read existing Agent Office registry.');

                return self::FAILURE;
            }
        }

        $tokenExpiry = now()->addDays($days);
        $tokenExpiryTimestamp = $tokenExpiry->timestamp;
        $secretBytes = random_bytes(32);
        $secretBase64 = base64_encode($secretBytes);
        $keyId = 'key_'.bin2hex(random_bytes(16));

        $newVersion = $existingSnapshot !== null ? ((int) $existingSnapshot['version'] + 1) : 1;
        $existingKeys = $existingSnapshot['keys'] ?? [];
        $existingAllowlist = $existingSnapshot['allowlist'] ?? [];

        $mergedAllowlist = $existingAllowlist;
        $knownCidrs = array_column($mergedAllowlist, 'cidr');
        foreach ($validatedCidrs as $cidr) {
            if (! in_array($cidr, $knownCidrs, true)) {
                $mergedAllowlist[] = [
                    'cidr' => $cidr,
                    'expires_at' => null,
                ];
                $knownCidrs[] = $cidr;
            }
        }

        DB::beginTransaction();
        $createdOutputFile = false;
        try {
            $principal = AgentOfficePrincipal::create([
                'name' => $name,
                'enabled' => true,
            ]);

            $tokenResult = $principal->createToken('agent-office', [$ability], $tokenExpiry);
            $plainToken = $tokenResult->plainTextToken;

            $newKeyEntry = [
                'secret' => $secretBase64,
                'principal_id' => $principal->id,
                'expires_at' => $tokenExpiryTimestamp,
                'enabled' => true,
            ];

            $mergedKeys = $existingKeys;
            $mergedKeys[$keyId] = $newKeyEntry;

            $maxExpiry = $tokenExpiryTimestamp;
            foreach ($mergedKeys as $k) {
                if (! empty($k['enabled']) && isset($k['expires_at']) && (int) $k['expires_at'] > $maxExpiry) {
                    $maxExpiry = (int) $k['expires_at'];
                }
            }

            $snapshot = [
                'version' => $newVersion,
                'enabled' => true,
                'expires_at' => $maxExpiry,
                'allowlist' => $mergedAllowlist,
                'keys' => $mergedKeys,
            ];

            $prevEnabled = config('agent_office.enabled');
            config(['agent_office.enabled' => true]);
            try {
                $security->publish($snapshot);
            } finally {
                config(['agent_office.enabled' => $prevEnabled]);
            }

            $credentials = [
                'principal_id' => $principal->id,
                'name' => $name,
                'key_id' => $keyId,
                'secret' => $secretBase64,
                'token' => $plainToken,
                'abilities' => [$ability],
                'expires_at' => $tokenExpiry->toIso8601String(),
                'cidrs' => $validatedCidrs,
            ];

            $json = json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $fh = @fopen($output, 'xb');
            if ($fh === false) {
                throw new \RuntimeException('Failed to open output file.');
            }
            $createdOutputFile = true;
            try {
                if (! chmod($output, 0600) || fwrite($fh, $json) !== strlen($json) || ! fflush($fh) || ! fsync($fh)) {
                    throw new \RuntimeException('Failed to write output file.');
                }
            } finally {
                fclose($fh);
            }
            @chmod($output, 0600);

            DB::commit();

            $this->info("Agent Office principal [{$principal->id}] provisioned successfully.");

            return self::SUCCESS;
        } catch (Throwable) {
            DB::rollBack();
            if ($createdOutputFile && (file_exists($output) || is_link($output))) {
                @unlink($output);
            }
            $this->error('Agent Office provisioning failed.');

            return self::FAILURE;
        }
    }

    private function validateAndCanonicalizeCidr(string $cidr): ?string
    {
        $parts = explode('/', $cidr);
        if (count($parts) !== 2) {
            return null;
        }
        [$ip, $prefixStr] = $parts;

        if (! ctype_digit($prefixStr) || $prefixStr !== (string) (int) $prefixStr) {
            return null;
        }
        $prefix = (int) $prefixStr;
        if ($prefix === 0) {
            return null;
        }

        $packed = @inet_pton($ip);
        if ($packed === false) {
            return null;
        }
        if (inet_ntop($packed) !== $ip) {
            return null;
        }

        if (strlen($packed) === 4) {
            if ($prefix < 1 || $prefix > 32) {
                return null;
            }
            $shift = 32 - $prefix;
            $hostMask = $shift === 32 ? 0xFFFFFFFF : ((1 << $shift) - 1);
            if ((ip2long($ip) & $hostMask) !== 0) {
                return null;
            }

            return $ip.'/'.$prefix;
        }

        if (strlen($packed) === 16) {
            if ($prefix < 1 || $prefix > 128) {
                return null;
            }
            $fullBytes = intdiv($prefix, 8);
            $remBits = $prefix % 8;
            $maskBytes = str_repeat("\xFF", $fullBytes);
            if ($remBits > 0) {
                $maskBytes .= chr((0xFF << (8 - $remBits)) & 0xFF);
            }
            $maskBytes = str_pad($maskBytes, 16, "\x00");
            if (($packed & ~$maskBytes) !== str_repeat("\x00", 16)) {
                return null;
            }

            return $ip.'/'.$prefix;
        }

        return null;
    }

    private function validateSecurityPath(?string $path): bool
    {
        if (! is_string($path) || trim($path) === '') {
            return false;
        }
        $norm = str_replace('\\', '/', $path);
        if (str_contains($norm, '/../') || str_ends_with($norm, '/..')) {
            return false;
        }
        $dir = dirname($path);
        if (! is_dir($dir) || is_link($dir)) {
            return false;
        }
        $realDir = realpath($dir);
        if ($realDir === false) {
            return false;
        }
        if (file_exists($path) && is_link($path)) {
            return false;
        }
        $public = realpath(public_path());
        if ($public !== false) {
            $normPublic = strtolower(str_replace('\\', '/', $public));
            $normDir = strtolower(str_replace('\\', '/', $realDir));
            if ($normDir === $normPublic || str_starts_with($normDir, $normPublic.'/')) {
                return false;
            }
        }

        return true;
    }
}
