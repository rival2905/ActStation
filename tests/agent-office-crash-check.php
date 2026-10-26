<?php

declare(strict_types=1);

use App\Services\AgentOfficeSecurity;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

class CrashableAgentOfficeSecurity extends AgentOfficeSecurity
{
    public function __construct(
        private readonly ?string $pauseEvent = null,
        private readonly ?string $readyFile = null
    ) {}

    public function audit(array $entry): void
    {
        $event = $entry['event'] ?? '';
        if ($this->pauseEvent !== null && $this->readyFile !== null) {
            if (in_array($this->pauseEvent, ['allowlist_applied', 'emergency_reenable'], true) && $event === $this->pauseEvent) {
                file_put_contents($this->readyFile, 'ready', LOCK_EX);
                while (true) {
                    usleep(50000);
                }
            }

            parent::audit($entry);

            if (in_array($this->pauseEvent, ['allowlist_publish_intent', 'emergency_reenable_intent'], true) && $event === $this->pauseEvent) {
                file_put_contents($this->readyFile, 'ready', LOCK_EX);
                while (true) {
                    usleep(50000);
                }
            }

            return;
        }

        parent::audit($entry);
    }
}

function crashContainer(array $config, ?string $pauseEvent = null, ?string $readyFile = null): AgentOfficeSecurity
{
    $app = new Application(dirname(__DIR__));
    Container::setInstance($app);
    $app->instance('config', new Repository([
        'agent_office' => $config,
        'app' => ['key' => 'base64:'.base64_encode(str_repeat('a', 32))],
    ]));
    $app->instance('path.public', dirname(__DIR__).'/public');

    return new CrashableAgentOfficeSecurity($pauseEvent, $readyFile);
}

function runRestartScenario(AgentOfficeSecurity $security, array $job): void
{
    $scenario = (string) $job['scenario'];
    $expectedVersion = (int) $job['expected_version'];
    $operatorId = (int) ($job['operator_id'] ?? 123);
    $replayPath = (string) $job['replay_path'];
    $replayBytes = (string) $job['replay_bytes'];

    $snapFalse = $security->snapshot(false);
    if (($snapFalse['version'] ?? null) !== $expectedVersion) {
        throw new RuntimeException($scenario.'_version_mismatch');
    }

    $denied = false;
    try {
        $security->snapshot(true);
    } catch (RuntimeException $e) {
        if (in_array($e->getMessage(), ['registry_change_pending', 'emergency_disabled'], true)) {
            $denied = true;
        } else {
            throw $e;
        }
    }
    if (! $denied) {
        throw new RuntimeException($scenario.'_snapshot_active_not_denied');
    }

    $security->reenable($expectedVersion, $operatorId);
    $snapTrue = $security->snapshot(true);
    if (($snapTrue['version'] ?? null) !== $expectedVersion) {
        throw new RuntimeException($scenario.'_restore_failed');
    }

    if ($scenario === 'postcommit') {
        $conflicted = false;
        try {
            $security->replaceAllowlist([['id' => 'stale', 'cidr' => '127.0.0.1/32', 'enabled' => true, 'expires_at' => null]], 1, $operatorId);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'registry_version_conflict') {
                $conflicted = true;
            }
        }
        if (! $conflicted) {
            throw new RuntimeException('old_version_publish_conflict_expected');
        }
    }

    if (file_get_contents($replayPath) !== $replayBytes) {
        throw new RuntimeException('replay_fixture_tampered');
    }
}

if (in_array('--child', $argv, true)) {
    try {
        $job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        $mode = $job['mode'] ?? '';

        if ($mode === 'worker') {
            $security = crashContainer($job['config'], $job['pause_event'] ?? null, $job['ready_file'] ?? null);
            if ($job['action'] === 'replace_allowlist') {
                $result = $security->replaceAllowlist(
                    $job['args']['entries'],
                    $job['args']['expected_version'],
                    $job['args']['operator_id']
                );
                echo json_encode(['status' => $result['status']], JSON_THROW_ON_ERROR);
                exit(0);
            }
            if ($job['action'] === 'reenable') {
                $security->reenable(
                    $job['args']['expected_version'],
                    $job['args']['operator_id']
                );
                echo json_encode(['status' => 'applied'], JSON_THROW_ON_ERROR);
                exit(0);
            }
            throw new RuntimeException('unknown_action');
        }

        if ($mode === 'restart_check') {
            $security = crashContainer($job['config']);
            runRestartScenario($security, $job);
            echo json_encode(['status' => 'applied'], JSON_THROW_ON_ERROR);
            exit(0);
        }

        throw new RuntimeException('unknown_mode');
    } catch (Throwable $error) {
        $msg = preg_match('/^[a-z0-9_:]+$/D', $error->getMessage()) ? $error->getMessage() : get_class($error);
        echo json_encode(['status' => 'failed', 'reason' => $msg], JSON_THROW_ON_ERROR);
        exit(1);
    }
}

function runCrashScenario(
    string $scenario,
    string $action,
    array $actionArgs,
    string $pauseEvent,
    int $expectedVersion,
    array $config,
    string $directory,
    string $replayPath,
    string $replayBytes,
    array &$children
): void {
    $readyFile = $directory.'/ready-'.$scenario.'.tmp';
    if (is_file($readyFile)) {
        unlink($readyFile);
    }

    $worker = new Process([PHP_BINARY, __FILE__, '--child']);
    $worker->setInput(json_encode([
        'mode' => 'worker',
        'config' => $config,
        'action' => $action,
        'args' => $actionArgs,
        'pause_event' => $pauseEvent,
        'ready_file' => $readyFile,
    ], JSON_THROW_ON_ERROR));
    $worker->setTimeout(30);
    $worker->start();
    $children[] = $worker;

    $ready = false;
    $start = microtime(true);
    while (microtime(true) - $start < 10.0) {
        if (is_file($readyFile) && filesize($readyFile) > 0) {
            $ready = true;
            break;
        }
        if (! $worker->isRunning()) {
            break;
        }
        usleep(20000);
    }
    if (! $ready) {
        throw new RuntimeException($scenario.'_worker_not_ready');
    }

    $worker->stop(0);

    $exitWaitStart = microtime(true);
    while ($worker->isRunning() && microtime(true) - $exitWaitStart < 5.0) {
        usleep(20000);
    }
    if ($worker->isRunning()) {
        throw new RuntimeException($scenario.'_worker_kill_failed');
    }

    if (is_file($readyFile)) {
        unlink($readyFile);
    }

    $restart = new Process([PHP_BINARY, __FILE__, '--child']);
    $restart->setInput(json_encode([
        'mode' => 'restart_check',
        'config' => $config,
        'scenario' => $scenario,
        'expected_version' => $expectedVersion,
        'operator_id' => 123,
        'replay_path' => $replayPath,
        'replay_bytes' => $replayBytes,
    ], JSON_THROW_ON_ERROR));
    $restart->setTimeout(30);
    $restart->run();
    $children[] = $restart;

    if (! $restart->isSuccessful()) {
        $output = json_decode($restart->getOutput(), true);
        $reason = $output['reason'] ?? 'unknown_failure';
        throw new RuntimeException($scenario.'_restart_failed:'.$reason);
    }
}

$scratch = str_replace('\\', '/', (string) getenv('TMPDIR'));
if (! is_dir($scratch) || ! str_ends_with(strtolower($scratch), '/hermes/cache/scratch')) {
    throw new RuntimeException('fixture_scratch_denied');
}
$directory = $scratch.'/gateway-crash-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$children = [];
$exit = 1;

try {
    if (PHP_OS_FAMILY === 'Windows') {
        $script = '$ErrorActionPreference="Stop";$p=$env:AGENT_OFFICE_ACL_DIRECTORY;$sid=[System.Security.Principal.WindowsIdentity]::GetCurrent().User;$acl=New-Object System.Security.AccessControl.DirectorySecurity;$acl.SetOwner($sid);$acl.SetAccessRuleProtection($true,$false);foreach($id in @($sid.Value,"S-1-5-18","S-1-5-32-544")){$s=New-Object System.Security.Principal.SecurityIdentifier($id);$r=New-Object System.Security.AccessControl.FileSystemAccessRule($s,"FullControl","ContainerInherit,ObjectInherit","None","Allow");$acl.AddAccessRule($r)};Set-Acl -LiteralPath $p -AclObject $acl';
        (new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $script], null, ['AGENT_OFFICE_ACL_DIRECTORY' => $directory]))->mustRun();
    }

    $config = [
        'enabled' => true,
        'private_acl_required' => true,
        'master_key' => base64_encode(random_bytes(32)),
        'registry_path' => $directory.'/registry.enc',
        'audit_path' => $directory.'/audit.jsonl',
    ];

    $security = crashContainer($config);
    $snapshot = [
        'version' => 1,
        'enabled' => true,
        'expires_at' => time() + 3600,
        'allowlist' => [],
        'keys' => [],
    ];
    $security->publish($snapshot);

    $replayPath = $directory.'/state.json';
    file_put_contents($replayPath, '{"nonce":"preserve-synthetic-replay"}');
    $replayBytes = file_get_contents($replayPath);

    // 1. Precommit publish crash
    runCrashScenario(
        'precommit',
        'replace_allowlist',
        [
            'entries' => [['id' => 'v2', 'cidr' => '127.0.0.1/32', 'enabled' => true, 'expires_at' => null]],
            'expected_version' => 1,
            'operator_id' => 123,
        ],
        'allowlist_publish_intent',
        1,
        $config,
        $directory,
        $replayPath,
        $replayBytes,
        $children
    );

    // 2. Postcommit publish crash
    runCrashScenario(
        'postcommit',
        'replace_allowlist',
        [
            'entries' => [['id' => 'v2', 'cidr' => '127.0.0.1/32', 'enabled' => true, 'expires_at' => null]],
            'expected_version' => 1,
            'operator_id' => 123,
        ],
        'allowlist_applied',
        2,
        $config,
        $directory,
        $replayPath,
        $replayBytes,
        $children
    );

    // 3. Re-enable crash seam: emergency_reenable_intent after parent audit
    $security->disable();
    runCrashScenario(
        'reenable_intent',
        'reenable',
        [
            'expected_version' => 2,
            'operator_id' => 123,
        ],
        'emergency_reenable_intent',
        2,
        $config,
        $directory,
        $replayPath,
        $replayBytes,
        $children
    );

    // 4. Re-enable crash seam: emergency_reenable before parent audit
    $security->disable();
    runCrashScenario(
        'reenable_applied',
        'reenable',
        [
            'expected_version' => 2,
            'operator_id' => 123,
        ],
        'emergency_reenable',
        2,
        $config,
        $directory,
        $replayPath,
        $replayBytes,
        $children
    );

    echo "Native crash acceptance: hard-kill precommit/postcommit and reenable seams; restart denial/readback; reenable recovery; replay fixture preserved; native private ACL active\n";
    $exit = 0;
} catch (Throwable $error) {
    fwrite(STDERR, 'crash_check_failed: '.(preg_match('/^[a-z0-9_:]+$/D', $error->getMessage()) ? $error->getMessage() : get_class($error))."\n");
} finally {
    foreach ($children as $child) {
        if ($child->isRunning()) {
            $child->stop(0);
        }
    }
    if (is_dir($directory)) {
        $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            if ($item->isFile() || $item->isLink()) {
                @unlink($item->getPathname());
            }
        }
        @rmdir($directory);
        if (is_dir($directory)) {
            $exit = 1;
        } else {
            echo "Crash acceptance cleanup readback: child processes exited, private directory absent\n";
        }
    }
}

exit($exit);
