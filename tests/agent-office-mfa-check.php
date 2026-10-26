<?php

declare(strict_types=1);

/**
 * Standalone real multiprocess MFA acceptance test.
 * Tests App\Services\AgentOfficeAdminAuth without Laravel app/.env/DB bootstrap.
 */

require_once dirname(__DIR__).'/vendor/autoload.php';

use App\Services\AgentOfficeAdminAuth;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Hashing\HashManager;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

// Minimal container class providing publicPath() for AgentOfficeSecurity::privatePath
class AgentOfficeMfaTestContainer extends Container
{
    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = $baseDir;
    }

    public function publicPath(string $path = ''): string
    {
        $public = $this->baseDir.'/public';

        return $path !== '' ? $public.'/'.$path : $public;
    }
}

function bootstrapMfaContainer(string $appKey, string $masterKey, string $mfaPath, string $baseDir): Container
{
    $container = new AgentOfficeMfaTestContainer($baseDir);
    Container::setInstance($container);
    Facade::setFacadeApplication($container);

    $config = new ConfigRepository([
        'app' => [
            'key' => $appKey,
        ],
        'agent_office' => [
            'master_key' => $masterKey,
            'admin_mfa_path' => $mfaPath,
            'private_acl_required' => false,
        ],
        'hashing' => [
            'driver' => 'bcrypt',
            'bcrypt' => ['rounds' => 4],
        ],
    ]);

    $container->instance('config', $config);
    $container->singleton('hash', fn ($app) => new HashManager($app));

    return $container;
}

// Child Worker Mode
if (in_array('--worker', $argv, true)) {
    $configPath = null;
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--config=')) {
            $configPath = substr($arg, 9);
            break;
        }
    }

    if ($configPath === null || ! is_file($configPath)) {
        fwrite(STDERR, "Worker missing valid config file\n");
        exit(2);
    }

    $rawConfig = file_get_contents($configPath);
    $job = json_decode($rawConfig, true, 32, JSON_THROW_ON_ERROR);

    bootstrapMfaContainer(
        (string) $job['app_key'],
        (string) $job['master_key'],
        (string) $job['mfa_path'],
        (string) $job['base_dir']
    );

    $auth = new AgentOfficeAdminAuth;
    $result = ['status' => 'denied', 'error' => null];
    $exitCode = 1;

    try {
        if (($job['action'] ?? '') === 'verify') {
            $auth->verify(
                (int) $job['user_id'],
                (string) $job['password'],
                (string) $job['otp'],
                (string) $job['password_hash']
            );
            $result = ['status' => 'ok', 'error' => null];
            $exitCode = 0;
        } else {
            $result = ['status' => 'error', 'error' => 'unknown_action'];
            $exitCode = 2;
        }
    } catch (Throwable $e) {
        $result = ['status' => 'denied', 'error' => $e->getMessage()];
        $exitCode = 1;
    }

    if (isset($job['result_path']) && is_string($job['result_path'])) {
        file_put_contents($job['result_path'], json_encode($result, JSON_THROW_ON_ERROR));
    }

    exit($exitCode);
}

// Main Test Runner Mode
$scratchBase = getenv('TMPDIR');
if (! is_string($scratchBase) || ! is_dir($scratchBase)) {
    $scratchBase = 'C:/Users/mr1ri/AppData/Local/hermes/cache/scratch';
}
$scratchBase = str_replace('\\', '/', realpath($scratchBase) ?: $scratchBase);
$runId = 'mfa-test-'.bin2hex(random_bytes(6));
$testScratch = $scratchBase.'/'.$runId;
if (! mkdir($testScratch, 0700, true) && ! is_dir($testScratch)) {
    fwrite(STDERR, "Failed to create test scratch dir: {$testScratch}\n");
    exit(1);
}

$baseDir = str_replace('\\', '/', dirname(__DIR__));
$mfaStorePath = $testScratch.'/admin_mfa.enc';

$appKey = 'base64:'.base64_encode(random_bytes(32));
$masterKey = base64_encode(random_bytes(32));
while ($masterKey === base64_encode(substr(base64_decode(substr($appKey, 7), true), 0, 32))) {
    $masterKey = base64_encode(random_bytes(32));
}

$counts = [
    'passed' => 0,
    'failed' => 0,
    'assertions' => 0,
];
$failures = [];

function assertTest(bool $condition, string $description, array &$counts, array &$failures): void
{
    $counts['assertions']++;
    if ($condition) {
        $counts['passed']++;
    } else {
        $counts['failed']++;
        $failures[] = $description;
    }
}

try {
    // Initialize container
    bootstrapMfaContainer($appKey, $masterKey, $mfaStorePath, $baseDir);
    $auth = new AgentOfficeAdminAuth;
    $google2fa = new Google2FA;

    $password = 'OperatorSecretPassword123!';
    $passwordHash = Hash::make($password);

    // Test 1: Enroll invalid user ID (< 1) must throw mfa_enrollment_denied
    $invalidEnrollOut = $testScratch.'/enroll_invalid.json';
    $threwInvalid = false;
    try {
        $auth->enroll(0, $invalidEnrollOut);
    } catch (RuntimeException $e) {
        $threwInvalid = ($e->getMessage() === 'mfa_enrollment_denied');
    }
    assertTest($threwInvalid, 'Enroll with userId < 1 throws mfa_enrollment_denied', $counts, $failures);

    // Test 2: Enroll private output for user 1
    $user1EnrollOut = $testScratch.'/enroll_user1.json';
    $auth->enroll(1, $user1EnrollOut);
    assertTest(is_file($user1EnrollOut), 'Enrollment output file created', $counts, $failures);

    $enrollData = json_decode((string) file_get_contents($user1EnrollOut), true);
    assertTest(
        is_array($enrollData) && ! empty($enrollData['secret']) && ! empty($enrollData['uri']),
        'Enrollment output contains valid secret and uri',
        $counts,
        $failures
    );
    $user1Secret = (string) ($enrollData['secret'] ?? '');

    // Test 3: isEnrolled state
    assertTest($auth->isEnrolled(1) === true, 'isEnrolled(1) is true after enrollment', $counts, $failures);
    assertTest($auth->isEnrolled(999) === false, 'isEnrolled(999) is false for non-enrolled user', $counts, $failures);

    // Test 4: Output existing denial
    $threwExistingOut = false;
    try {
        $auth->enroll(10, $user1EnrollOut);
    } catch (RuntimeException $e) {
        $threwExistingOut = ($e->getMessage() === 'mfa_enrollment_denied');
    }
    assertTest($threwExistingOut, 'Enroll with existing output file throws mfa_enrollment_denied', $counts, $failures);

    // Test 5: Already enrolled denial
    $user1BisOut = $testScratch.'/enroll_user1_bis.json';
    $threwAlreadyEnrolled = false;
    try {
        $auth->enroll(1, $user1BisOut);
    } catch (RuntimeException $e) {
        $threwAlreadyEnrolled = ($e->getMessage() === 'mfa_already_enrolled');
    }
    assertTest($threwAlreadyEnrolled, 'Enroll already enrolled user throws mfa_already_enrolled', $counts, $failures);

    // Test 6: Correct password and TOTP verification
    $otp1 = $google2fa->getCurrentOtp($user1Secret);
    $threwVerifyOk = false;
    try {
        $auth->verify(1, $password, $otp1, $passwordHash);
    } catch (Throwable) {
        $threwVerifyOk = true;
    }
    assertTest(! $threwVerifyOk, 'Verify with correct password and TOTP succeeds', $counts, $failures);

    // Test 7: Replay same OTP immediately denied
    $threwReplay = false;
    try {
        $auth->verify(1, $password, $otp1, $passwordHash);
    } catch (RuntimeException $e) {
        $threwReplay = ($e->getMessage() === 'mfa_verification_denied');
    }
    assertTest($threwReplay, 'Replay of same OTP throws mfa_verification_denied', $counts, $failures);

    // Test 8: Wrong password denied
    $freshOtp1 = $google2fa->getCurrentOtp($user1Secret);
    $threwWrongPass = false;
    try {
        $auth->verify(1, 'WrongPassword999!', $freshOtp1, $passwordHash);
    } catch (RuntimeException $e) {
        $threwWrongPass = ($e->getMessage() === 'mfa_verification_denied');
    }
    assertTest($threwWrongPass, 'Verify with wrong password throws mfa_verification_denied', $counts, $failures);

    // Test 9: Wrong OTP denied
    $threwWrongOtp = false;
    try {
        $auth->verify(1, $password, '000000', $passwordHash);
    } catch (RuntimeException $e) {
        $threwWrongOtp = ($e->getMessage() === 'mfa_verification_denied');
    }
    assertTest($threwWrongOtp, 'Verify with invalid OTP throws mfa_verification_denied', $counts, $failures);

    $expiredOutput = $testScratch.'/expired-enrollment.json';
    $auth->enroll(4, $expiredOutput);
    $expiredSecret = json_decode(file_get_contents($expiredOutput), true, flags: JSON_THROW_ON_ERROR)['secret'];
    $oldOtp = (new Google2FA)->oathTotp($expiredSecret, intdiv(time(), 30) - 4);
    $expiredDenied = false;
    try {
        $auth->verify(4, $password, $oldOtp, $passwordHash);
    } catch (RuntimeException $e) {
        $expiredDenied = $e->getMessage() === 'mfa_verification_denied';
    }
    assertTest($expiredDenied, 'Expired TOTP outside accepted window denied', $counts, $failures);

    // Test 10: Multiprocess race condition on same OTP (User 2)
    // Enroll User 2
    $user2EnrollOut = $testScratch.'/enroll_user2.json';
    $auth->enroll(2, $user2EnrollOut);
    $user2Data = json_decode((string) file_get_contents($user2EnrollOut), true);
    $user2Secret = (string) ($user2Data['secret'] ?? '');

    // Ensure we are not on the edge of a 30s window
    $secLeft = 30 - (time() % 30);
    if ($secLeft < 4) {
        sleep($secLeft + 1);
    }
    $otp2 = $google2fa->getCurrentOtp($user2Secret);

    // Child config files (no secrets passed in CLI argv or printed to stdout)
    $worker1ReqPath = $testScratch.'/worker1_req.json';
    $worker1ResPath = $testScratch.'/worker1_res.json';
    file_put_contents($worker1ReqPath, json_encode([
        'app_key' => $appKey,
        'master_key' => $masterKey,
        'mfa_path' => $mfaStorePath,
        'base_dir' => $baseDir,
        'action' => 'verify',
        'user_id' => 2,
        'password' => $password,
        'password_hash' => $passwordHash,
        'otp' => $otp2,
        'result_path' => $worker1ResPath,
    ], JSON_THROW_ON_ERROR));

    $worker2ReqPath = $testScratch.'/worker2_req.json';
    $worker2ResPath = $testScratch.'/worker2_res.json';
    file_put_contents($worker2ReqPath, json_encode([
        'app_key' => $appKey,
        'master_key' => $masterKey,
        'mfa_path' => $mfaStorePath,
        'base_dir' => $baseDir,
        'action' => 'verify',
        'user_id' => 2,
        'password' => $password,
        'password_hash' => $passwordHash,
        'otp' => $otp2,
        'result_path' => $worker2ResPath,
    ], JSON_THROW_ON_ERROR));

    $phpBin = PHP_BINARY;
    $selfScript = __FILE__;

    // Spawn 2 concurrent processes
    $cmd1 = [$phpBin, $selfScript, '--worker', '--config='.$worker1ReqPath];
    $cmd2 = [$phpBin, $selfScript, '--worker', '--config='.$worker2ReqPath];

    $pipes1 = [];
    $p1 = proc_open($cmd1, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes1);
    $pipes2 = [];
    $p2 = proc_open($cmd2, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes2);

    $out1 = stream_get_contents($pipes1[1]);
    $err1 = stream_get_contents($pipes1[2]);
    fclose($pipes1[0]);
    fclose($pipes1[1]);
    fclose($pipes1[2]);
    $code1 = proc_close($p1);

    $out2 = stream_get_contents($pipes2[1]);
    $err2 = stream_get_contents($pipes2[2]);
    fclose($pipes2[0]);
    fclose($pipes2[1]);
    fclose($pipes2[2]);
    $code2 = proc_close($p2);

    $res1 = json_decode((string) @file_get_contents($worker1ResPath), true);
    $res2 = json_decode((string) @file_get_contents($worker2ResPath), true);

    $successCount = (($res1['status'] ?? '') === 'ok' ? 1 : 0) + (($res2['status'] ?? '') === 'ok' ? 1 : 0);
    $deniedCount = (($res1['status'] ?? '') === 'denied' ? 1 : 0) + (($res2['status'] ?? '') === 'denied' ? 1 : 0);

    assertTest(
        $successCount === 1 && $deniedCount === 1,
        'Two concurrent processes on same OTP result in exactly one winner and one denied',
        $counts,
        $failures
    );

    // Verify no secret leak on stdout or stderr
    assertTest(
        ! str_contains($out1.$err1.$out2.$err2, $password)
        && ! str_contains($out1.$err1.$out2.$err2, $user2Secret)
        && ! str_contains($out1.$err1.$out2.$err2, $otp2),
        'No secrets leaked on child stdout or stderr',
        $counts,
        $failures
    );

    // Test 11: Multiprocess restart replay denial
    // Spawn third independent process trying to verify that same OTP
    $worker3ReqPath = $testScratch.'/worker3_req.json';
    $worker3ResPath = $testScratch.'/worker3_res.json';
    file_put_contents($worker3ReqPath, json_encode([
        'app_key' => $appKey,
        'master_key' => $masterKey,
        'mfa_path' => $mfaStorePath,
        'base_dir' => $baseDir,
        'action' => 'verify',
        'user_id' => 2,
        'password' => $password,
        'password_hash' => $passwordHash,
        'otp' => $otp2,
        'result_path' => $worker3ResPath,
    ], JSON_THROW_ON_ERROR));

    $cmd3 = [$phpBin, $selfScript, '--worker', '--config='.$worker3ReqPath];
    $pipes3 = [];
    $p3 = proc_open($cmd3, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes3);
    $out3 = stream_get_contents($pipes3[1]);
    $err3 = stream_get_contents($pipes3[2]);
    fclose($pipes3[0]);
    fclose($pipes3[1]);
    fclose($pipes3[2]);
    $code3 = proc_close($p3);

    $res3 = json_decode((string) @file_get_contents($worker3ResPath), true);
    assertTest(
        $code3 !== 0 && ($res3['status'] ?? '') === 'denied' && ($res3['error'] ?? '') === 'mfa_verification_denied',
        'Third process replay denial on restart after race',
        $counts,
        $failures
    );

    // Test 12: Persistent 5-fail rate limit restart (User 3)
    $user3EnrollOut = $testScratch.'/enroll_user3.json';
    $auth->enroll(3, $user3EnrollOut);
    $user3Data = json_decode((string) file_get_contents($user3EnrollOut), true);
    $user3Secret = (string) ($user3Data['secret'] ?? '');

    // 4 failed attempts
    for ($i = 1; $i <= 4; $i++) {
        $threw = false;
        try {
            $auth->verify(3, $password, '000000', $passwordHash);
        } catch (RuntimeException $e) {
            $threw = ($e->getMessage() === 'mfa_verification_denied');
        }
        assertTest($threw, "Pre-lockout failure {$i} denied", $counts, $failures);
    }

    // 5th failed attempt triggers rate limit
    $threw5 = false;
    try {
        $auth->verify(3, $password, '000000', $passwordHash);
    } catch (RuntimeException $e) {
        $threw5 = ($e->getMessage() === 'mfa_verification_denied');
    }
    assertTest($threw5, '5th failure triggers rate limit lockout', $counts, $failures);

    // Spawn a fresh restarted child process with CORRECT password and VALID OTP
    $validOtp3 = $google2fa->getCurrentOtp($user3Secret);
    $workerLockoutReqPath = $testScratch.'/worker_lockout_req.json';
    $workerLockoutResPath = $testScratch.'/worker_lockout_res.json';
    file_put_contents($workerLockoutReqPath, json_encode([
        'app_key' => $appKey,
        'master_key' => $masterKey,
        'mfa_path' => $mfaStorePath,
        'base_dir' => $baseDir,
        'action' => 'verify',
        'user_id' => 3,
        'password' => $password,
        'password_hash' => $passwordHash,
        'otp' => $validOtp3,
        'result_path' => $workerLockoutResPath,
    ], JSON_THROW_ON_ERROR));

    $cmdLockout = [$phpBin, $selfScript, '--worker', '--config='.$workerLockoutReqPath];
    $pipesLockout = [];
    $pLockout = proc_open($cmdLockout, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipesLockout);
    $outLockout = stream_get_contents($pipesLockout[1]);
    $errLockout = stream_get_contents($pipesLockout[2]);
    fclose($pipesLockout[0]);
    fclose($pipesLockout[1]);
    fclose($pipesLockout[2]);
    $codeLockout = proc_close($pLockout);

    $resLockout = json_decode((string) @file_get_contents($workerLockoutResPath), true);
    assertTest(
        $codeLockout !== 0 && ($resLockout['status'] ?? '') === 'denied' && ($resLockout['error'] ?? '') === 'mfa_verification_denied',
        'Fresh restarted process denied under persistent 5-fail rate limit even with valid credentials',
        $counts,
        $failures
    );

} finally {
    // Test 13: Cleanup finally read-back
    $cleanupSuccess = true;
    if (is_dir($testScratch)) {
        $files = scandir($testScratch) ?: [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $target = $testScratch.'/'.$file;
            if (is_file($target)) {
                @unlink($target);
            }
        }
        @rmdir($testScratch);
        $cleanupSuccess = ! is_dir($testScratch);
    }
    assertTest($cleanupSuccess, 'Cleanup finally read-back verified directory deleted', $counts, $failures);
}

// Output summary as JSON
$report = [
    'status' => $counts['failed'] === 0 ? 'success' : 'failed',
    'total_assertions' => $counts['assertions'],
    'passed' => $counts['passed'],
    'failed' => $counts['failed'],
    'failures' => $failures,
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
exit($counts['failed'] === 0 ? 0 : 1);
