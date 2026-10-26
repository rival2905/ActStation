<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class AgentOfficePrivateDirectory
{
    public static function verify(string $directory): void
    {
        if (! is_dir($directory) || is_link($directory)) {
            throw new RuntimeException('private_acl_denied');
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            $paths = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ([$directory, ...$paths] as $path) {
                $path = (string) $path;
                if (is_link($path) || (fileperms($path) & 0077) !== 0 || fileowner($path) !== fileowner($directory)) {
                    throw new RuntimeException('private_acl_denied');
                }
            }

            return;
        }
        // Verify native DACL and owner, including existing child files. chmod is not an ACL boundary on Windows.
        $script = <<<'PS'
$p=$env:AGENT_OFFICE_ACL_DIRECTORY; $ErrorActionPreference='Stop';
$sid=[System.Security.Principal.WindowsIdentity]::GetCurrent().User.Value;
$allowed=@($sid,'S-1-5-18','S-1-5-32-544');
$parent=Get-Item -LiteralPath $p;
while($null -ne $parent){if(($parent.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){exit 2};$parent=$parent.Parent}
$items=@(Get-Item -LiteralPath $p)+@(Get-ChildItem -LiteralPath $p -Force -Recurse);
foreach($i in $items){
 if(($i.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){exit 2}
 $a=Get-Acl -LiteralPath $i.FullName;
 if(($i.FullName -eq (Get-Item -LiteralPath $p).FullName) -and !$a.AreAccessRulesProtected){exit 2}
 if($allowed -notcontains $a.GetOwner([System.Security.Principal.SecurityIdentifier]).Value){exit 2}
 $rules=$a.GetAccessRules($true,$true,[System.Security.Principal.SecurityIdentifier]);
 foreach($r in $rules){if($r.AccessControlType -eq 'Allow' -and $allowed -notcontains $r.IdentityReference.Value){exit 2}}
}; Write-Output 'private_acl_verified'
PS;
        $environment = ['AGENT_OFFICE_ACL_DIRECTORY' => $directory];
        foreach (['SystemRoot', 'WINDIR', 'USERPROFILE', 'APPDATA', 'LOCALAPPDATA', 'TEMP', 'TMP', 'PATH'] as $name) {
            $value = getenv($name, true);
            if ($value !== false) {
                $environment[$name] = $value;
            }
        }
        $process = new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $script], null, $environment);
        $process->setTimeout(15)->run();
        if (! $process->isSuccessful() || trim($process->getOutput()) !== 'private_acl_verified') {
            throw new RuntimeException('private_acl_denied');
        }
    }
}
