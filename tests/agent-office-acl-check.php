<?php

declare(strict_types=1);

use App\Services\AgentOfficePrivateDirectory;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';
$root = str_replace('\\', '/', (string) getenv('TMPDIR'));
if (! str_ends_with(strtolower($root), '/hermes/cache/scratch')) {
    throw new RuntimeException('scratch_denied');
}
$dir = $root.'/gateway-acl-'.bin2hex(random_bytes(8));
mkdir($dir, 0700);
$exit = 1;
try {
    if (PHP_OS_FAMILY === 'Windows') {
        $ps = '$p=$env:AGENT_OFFICE_ACL_DIRECTORY; $sid=[System.Security.Principal.WindowsIdentity]::GetCurrent().User; $acl=New-Object System.Security.AccessControl.DirectorySecurity; $acl.SetOwner($sid); $acl.SetAccessRuleProtection($true,$false); foreach($id in @($sid.Value,"S-1-5-18","S-1-5-32-544")){ $s=New-Object System.Security.Principal.SecurityIdentifier($id); $r=New-Object System.Security.AccessControl.FileSystemAccessRule($s,"FullControl","ContainerInherit,ObjectInherit","None","Allow"); $acl.AddAccessRule($r) }; Set-Acl -LiteralPath $p -AclObject $acl';
        (new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', '$ErrorActionPreference="Stop";'.$ps], null, ['AGENT_OFFICE_ACL_DIRECTORY' => $dir]))->mustRun();
    }
    AgentOfficePrivateDirectory::verify($dir);
    file_put_contents($dir.'/registry.enc', 'SYNTHETIC-PRIVATE');
    chmod($dir.'/registry.enc', 0600);
    AgentOfficePrivateDirectory::verify($dir);
    file_put_contents($dir.'/.hidden', 'SYNTHETIC-PRIVATE');
    chmod($dir.'/.hidden', 0600);
    AgentOfficePrivateDirectory::verify($dir);
    if (PHP_OS_FAMILY === 'Windows') {
        (new Process(['icacls.exe', $dir.'/.hidden', '/grant', '*S-1-1-0:(RX)']))->mustRun();
    } else {
        chmod($dir.'/.hidden', 0777);
    }
    clearstatcache();
    $hiddenDenied = false;
    try {
        AgentOfficePrivateDirectory::verify($dir);
    } catch (RuntimeException) {
        $hiddenDenied = true;
    }
    if (! $hiddenDenied) {
        throw new RuntimeException('hidden_acl_accepted');
    }
    unlink($dir.'/.hidden');
    if (PHP_OS_FAMILY === 'Windows') {
        (new Process(['icacls.exe', $dir, '/grant', '*S-1-1-0:(OI)(CI)(RX)']))->mustRun();
    } else {
        chmod($dir, 0755);
    }
    $denied = false;
    try {
        AgentOfficePrivateDirectory::verify($dir);
    } catch (RuntimeException) {
        $denied = true;
    }
    if (! $denied) {
        throw new RuntimeException('broad_acl_accepted');
    }
    echo "native ACL: protected private directory/file accepted; hidden file broad access denied; Everyone grant rejected\n";
    $exit = 0;
} finally {
    foreach (new FilesystemIterator($dir) as $p) {
        unlink($p->getPathname());
    }
    rmdir($dir);
    if (is_dir($dir)) {
        throw new RuntimeException('cleanup_failed');
    }
    echo "ACL fixture cleanup readback: directory absent\n";
}
exit($exit);
