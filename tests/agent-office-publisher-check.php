<?php

declare(strict_types=1);

use App\Services\AgentOfficeSecurity;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

function publisherContainer(array $config): AgentOfficeSecurity
{
    $app = new Application(dirname(__DIR__));
    Container::setInstance($app);
    $app->instance('config', new Repository(['agent_office' => $config, 'app' => ['key' => 'base64:'.base64_encode(str_repeat('a', 32))]]));
    $app->instance('path.public', dirname(__DIR__).'/public');

    return new AgentOfficeSecurity;
}

if (in_array('--child', $argv, true)) {
    try {
        $job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        $result = publisherContainer($job['config'])->replaceAllowlist($job['entries'], 1, 123);
        echo $result['status'];
        exit($result['status'] === 'applied' ? 0 : 1);
    } catch (Throwable $error) {
        echo $error->getMessage() === 'registry_version_conflict' ? 'conflict' : 'failed';
        exit(1);
    }
}

$scratch = str_replace('\\', '/', (string) getenv('TMPDIR'));
if (! is_dir($scratch) || ! str_ends_with(strtolower($scratch), '/hermes/cache/scratch')) {
    throw new RuntimeException('fixture_scratch_denied');
}
$directory = $scratch.'/gateway-publisher-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$children = [];
$exit = 1;
try {
    if (PHP_OS_FAMILY === 'Windows') {
        $script = '$ErrorActionPreference="Stop";$p=$env:AGENT_OFFICE_ACL_DIRECTORY;$sid=[System.Security.Principal.WindowsIdentity]::GetCurrent().User;$acl=New-Object System.Security.AccessControl.DirectorySecurity;$acl.SetOwner($sid);$acl.SetAccessRuleProtection($true,$false);foreach($id in @($sid.Value,"S-1-5-18","S-1-5-32-544")){$s=New-Object System.Security.Principal.SecurityIdentifier($id);$r=New-Object System.Security.AccessControl.FileSystemAccessRule($s,"FullControl","ContainerInherit,ObjectInherit","None","Allow");$acl.AddAccessRule($r)};Set-Acl -LiteralPath $p -AclObject $acl';
        (new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $script], null, ['AGENT_OFFICE_ACL_DIRECTORY' => $directory]))->mustRun();
    }
    $config = ['enabled' => true, 'private_acl_required' => true, 'master_key' => base64_encode(random_bytes(32)), 'registry_path' => $directory.'/registry.enc', 'audit_path' => $directory.'/audit.jsonl'];
    $security = publisherContainer($config);
    $snapshot = ['version' => 1, 'enabled' => true, 'expires_at' => time() + 3600, 'allowlist' => [], 'keys' => []];
    $security->publish($snapshot);
    $entries = [['id' => 'race', 'cidr' => '127.0.0.1/32', 'label' => 'Synthetic race', 'enabled' => true, 'expires_at' => null]];
    $job = json_encode(compact('config', 'entries'), JSON_THROW_ON_ERROR);
    $lock = fopen($directory.'/registry.enc.lock', 'c+b');
    flock($lock, LOCK_EX);
    try {
        for ($i = 0; $i < 2; $i++) {
            $child = new Process([PHP_BINARY, __FILE__, '--child']);
            $child->setInput($job)->setTimeout(40)->start();
            $children[] = $child;
        }
        usleep(300000);
        if (! $children[0]->isRunning() || ! $children[1]->isRunning()) {
            throw new RuntimeException('publish_contender_not_running');
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    $results = [];
    foreach ($children as $child) {
        $child->wait();
        $results[] = $child->getOutput();
    }
    sort($results);
    if ($results !== ['applied', 'conflict'] || $security->snapshot(false)['version'] !== 2) {
        throw new RuntimeException('publish_race_failed');
    }
    $audit = file_get_contents($directory.'/audit.jsonl');
    if (substr_count($audit, 'allowlist_publish_intent') !== 1 || substr_count($audit, 'allowlist_applied') !== 1) {
        throw new RuntimeException('publish_audit_failed');
    }
    $encrypted = file_get_contents($directory.'/registry.enc');
    file_put_contents($directory.'/state.json', '{"nonce":"preserve-synthetic-replay"}');
    $replay = file_get_contents($directory.'/state.json');
    file_put_contents($directory.'/registry.enc', 'corrupt');
    $brokenConfig = $config;
    $brokenConfig['master_key'] = null;
    publisherContainer($brokenConfig)->disable();
    if (! is_file($directory.'/registry.enc.disabled') || file_get_contents($directory.'/state.json') !== $replay) {
        throw new RuntimeException('breakglass_failed');
    }
    file_put_contents($directory.'/registry.enc', $encrypted);
    $security = publisherContainer($config);
    $security->reenable(2, 123);
    if ($security->snapshot()['version'] !== 2 || count(glob($directory.'/registry.enc.disabled.*.audit')) !== 1 || file_get_contents($directory.'/state.json') !== $replay) {
        throw new RuntimeException('reenable_failed');
    }
    echo "Native publisher: two processes one version winner; audit/readback2; break-glass corrupt registry/no key/no DB; re-enable preserves replay; native private ACL active\n";
    $exit = 0;
} catch (Throwable $error) {
    fwrite(STDERR, 'publisher_check_failed: '.(preg_match('/^[a-z_]+$/D', $error->getMessage()) ? $error->getMessage() : get_class($error))."\n");
} finally {
    foreach ($children as $child) {
        if ($child->isRunning()) {
            $child->stop(1);
        }
    }
    foreach (glob($directory.'/*') as $file) {
        unlink($file);
    }
    rmdir($directory);
    if (is_dir($directory)) {
        $exit = 1;
    } else {
        echo "Publisher cleanup readback: child processes exited, private directory absent\n";
    }
}
exit($exit);
