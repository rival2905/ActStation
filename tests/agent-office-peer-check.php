<?php

declare(strict_types=1);

use App\Services\AgentOfficePeer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

require dirname(__DIR__).'/vendor/autoload.php';

function assertPeerCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

function assertThrowsPeerException(Closure $callback, string $case): void
{
    try {
        $callback();
    } catch (RuntimeException $e) {
        return;
    } catch (Throwable $e) {
        throw new RuntimeException("Expected RuntimeException for '{$case}', got: ".get_class($e));
    }
    throw new RuntimeException("Expected RuntimeException was not thrown for: '{$case}'");
}

$testsRun = 0;

echo "Starting AgentOfficePeer standalone perimeter verification...\n";

// 1. Trusted proxies list validation: explicit exact canonical CIDRs only
$invalidProxyConfigs = [
    'missing mask' => ['10.0.0.1'],
    'wildcard star' => ['*'],
    'wildcard ipv4 zero' => ['0.0.0.0/0'],
    'wildcard ipv6 zero' => ['::/0'],
    'non-zero host bits ipv4' => ['192.168.1.5/24'],
    'non-zero host bits ipv6' => ['2001:db8::1/64'],
    'non-string element' => [123],
    'invalid ip cidr' => ['999.999.999.999/24'],
    'malformed cidr string' => ['not-a-cidr'],
    'empty cidr' => [''],
];

foreach ($invalidProxyConfigs as $case => $invalidProxies) {
    $testsRun++;
    assertThrowsPeerException(function () use ($invalidProxies) {
        $req = Request::create('https://example.com/api', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTPS' => 'on',
        ]);
        AgentOfficePeer::resolve($req, $invalidProxies);
    }, "Invalid trusted proxies configuration ({$case})");
}

// 2. Direct peer REMOTE_ADDR validation
$invalidPeers = [
    'missing remote addr' => null,
    'malformed remote addr' => 'not-an-ip',
    'remote addr with port' => '10.0.0.1:8080',
    'non-canonical ipv4 octal' => '010.0.0.1',
    'non-canonical ipv4 leading zero' => '127.000.000.001',
    'non-canonical ipv6 uncompressed' => '2001:0db8::1',
    'non-canonical ipv6 uppercase' => '2001:DB8::1',
];

foreach ($invalidPeers as $case => $badIp) {
    $testsRun++;
    assertThrowsPeerException(function () use ($badIp) {
        $server = ['HTTPS' => 'on'];
        if ($badIp !== null) {
            $server['REMOTE_ADDR'] = $badIp;
        }
        $req = Request::create('https://example.com/api', 'GET', [], [], [], $server);
        if ($badIp === null) {
            $req->server->remove('REMOTE_ADDR');
        }
        AgentOfficePeer::resolve($req, ['10.0.0.0/24']);
    }, "Direct peer validation ({$case})");
}

// 3. Untrusted direct peer handling (IPv4 and IPv6)
// Case 3a: Direct IPv4 HTTPS untrusted peer, no forward headers
$testsRun++;
$req3a = Request::create('https://example.com/api', 'GET', [], [], [], [
    'REMOTE_ADDR' => '198.51.100.10',
    'HTTPS' => 'on',
]);
$res3a = AgentOfficePeer::resolve($req3a, ['10.0.0.0/24']);
assertPeerCheck($res3a === ['ip' => '198.51.100.10', 'secure' => true], 'Case 3a: Untrusted direct IPv4 over HTTPS');

// Case 3b: Direct IPv4 HTTP untrusted peer, no forward headers
$testsRun++;
$req3b = Request::create('http://example.com/api', 'GET', [], [], [], [
    'REMOTE_ADDR' => '198.51.100.10',
]);
$res3b = AgentOfficePeer::resolve($req3b, ['10.0.0.0/24']);
assertPeerCheck($res3b === ['ip' => '198.51.100.10', 'secure' => false], 'Case 3b: Untrusted direct IPv4 over HTTP');

// Case 3c: Direct IPv6 HTTPS untrusted peer
$testsRun++;
$req3c = Request::create('https://example.com/api', 'GET', [], [], [], [
    'REMOTE_ADDR' => '2001:db8:85a3::8a2e:370:7334',
    'HTTPS' => '1',
]);
$res3c = AgentOfficePeer::resolve($req3c, ['10.0.0.0/24']);
assertPeerCheck($res3c === ['ip' => '2001:db8:85a3::8a2e:370:7334', 'secure' => true], 'Case 3c: Untrusted direct IPv6 over HTTPS');

// Case 3d: Untrusted direct peer with spoofed forwarding headers over HTTP
$testsRun++;
$req3d = Request::create('http://example.com/api', 'GET', [], [], [], [
    'REMOTE_ADDR' => '198.51.100.10',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
    'HTTP_X_FORWARDED_PROTO' => 'https',
    'HTTP_FORWARDED' => 'for=203.0.113.50;proto=https',
    'HTTP_X_FORWARDED_HOST' => 'evil.example.com',
]);
$res3d = AgentOfficePeer::resolve($req3d, ['10.0.0.0/24']);
assertPeerCheck($res3d === ['ip' => '198.51.100.10', 'secure' => false], 'Case 3d: Untrusted direct peer spoofed headers ignored');

// 4. Untrusted direct peer with spoofed Laravel global proxy trust
$testsRun++;
Request::setTrustedProxies(
    ['192.168.1.100'],
    SymfonyRequest::HEADER_X_FORWARDED_FOR | SymfonyRequest::HEADER_X_FORWARDED_PROTO
);
$req4 = Request::create('http://example.com/api', 'GET', [], [], [], [
    'REMOTE_ADDR' => '192.168.1.100',
    'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
    'HTTP_X_FORWARDED_PROTO' => 'https',
]);
// Verify that Laravel itself is spoofed:
assertPeerCheck($req4->isSecure() === true, 'Precondition: Laravel global state fooled into isSecure=true');
assertPeerCheck($req4->ip() === '1.2.3.4', 'Precondition: Laravel global state fooled into ip=1.2.3.4');

// AgentOfficePeer must NOT be fooled; 192.168.1.100 is not in perimeter trusted CIDRs
$res4 = AgentOfficePeer::resolve($req4, ['10.0.0.0/24']);
assertPeerCheck($res4 === ['ip' => '192.168.1.100', 'secure' => false], 'Case 4: HTTP spoofed Laravel global trust bypassed');

// Reset Laravel global trusted proxies
Request::setTrustedProxies([], 0);

// 5. Trusted proxy peer perimeter enforcement
// Case 5a: Valid single-hop IPv4 client through trusted IPv4 proxy
$testsRun++;
$req5a = Request::create('http://upstream/api', 'GET', [], [], [], [
    'REMOTE_ADDR' => '10.0.0.5',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
    'HTTP_X_FORWARDED_PROTO' => 'https',
]);
$res5a = AgentOfficePeer::resolve($req5a, ['10.0.0.0/24']);
assertPeerCheck($res5a === ['ip' => '203.0.113.195', 'secure' => true], 'Case 5a: Valid IPv4 client via trusted proxy');

// Case 5b: Valid single-hop IPv6 client through trusted IPv4 proxy
$testsRun++;
$req5b = Request::create('http://upstream/api', 'GET', [], [], [], [
    'REMOTE_ADDR' => '10.0.0.5',
    'HTTP_X_FORWARDED_FOR' => '2001:db8::1',
    'HTTP_X_FORWARDED_PROTO' => 'https',
]);
$res5b = AgentOfficePeer::resolve($req5b, ['10.0.0.0/24']);
assertPeerCheck($res5b === ['ip' => '2001:db8::1', 'secure' => true], 'Case 5b: Valid IPv6 client via trusted proxy');

// Case 5c: Valid IPv4 client through trusted IPv6 proxy
$testsRun++;
$req5c = Request::create('http://upstream/api', 'GET', [], [], [], [
    'REMOTE_ADDR' => 'fd00::1',
    'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
    'HTTP_X_FORWARDED_PROTO' => 'https',
]);
$res5c = AgentOfficePeer::resolve($req5c, ['fd00::/64']);
assertPeerCheck($res5c === ['ip' => '198.51.100.20', 'secure' => true], 'Case 5c: Valid client via trusted IPv6 proxy');

// 6. Trusted proxy peer denials and perimeter violations
$trustedDenialCases = [
    'missing X-Forwarded-For' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ],
    ],
    'missing X-Forwarded-Proto' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
        ],
    ],
    'X-Forwarded-Proto is http' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
            'HTTP_X_FORWARDED_PROTO' => 'http',
        ],
    ],
    'multi-hop chain with comma' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195, 10.0.0.2',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ],
    ],
    'multi-hop chain with space' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195 10.0.0.2',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ],
    ],
    'non-canonical octal XFF' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '010.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ],
    ],
    'non-canonical uncompressed IPv6 XFF' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '2001:0db8::1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ],
    ],
    'non-canonical uppercase IPv6 XFF' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '2001:DB8::1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ],
    ],
    'malformed IP in XFF' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => 'invalid-ip',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ],
    ],
    'IP with port in XFF' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195:443',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ],
    ],
    'whitespace padded XFF' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => ' 203.0.113.195 ',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ],
    ],
    'forbidden Forwarded header present' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_FORWARDED' => 'for=203.0.113.195;proto=https',
        ],
    ],
    'forbidden X-Forwarded-Host present' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'internal.host',
        ],
    ],
    'forbidden X-Forwarded-Port present' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ],
    ],
    'forbidden X-Forwarded-Prefix present' => [
        'server' => [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PREFIX' => '/api',
        ],
    ],
];

foreach ($trustedDenialCases as $case => $spec) {
    $testsRun++;
    assertThrowsPeerException(function () use ($spec) {
        $req = Request::create('http://upstream/api', 'GET', [], [], [], $spec['server']);
        AgentOfficePeer::resolve($req, ['10.0.0.0/24']);
    }, "Trusted denial: {$case}");
}

// Case 6b: Duplicate header instances (array in headers bag)
$testsRun++;
assertThrowsPeerException(function () {
    $req = Request::create('http://upstream/api', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.5',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);
    $req->headers->set('X-Forwarded-For', ['203.0.113.195', '10.0.0.2']);
    AgentOfficePeer::resolve($req, ['10.0.0.0/24']);
}, 'Trusted denial: duplicate X-Forwarded-For headers');

$testsRun++;
assertThrowsPeerException(function () {
    $req = Request::create('http://upstream/api', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.5',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
    ]);
    $req->headers->set('X-Forwarded-Proto', ['https', 'https']);
    AgentOfficePeer::resolve($req, ['10.0.0.0/24']);
}, 'Trusted denial: duplicate X-Forwarded-Proto headers');

$testsRun++;
assertThrowsPeerException(function () {
    $req = Request::create('http://upstream/api', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.5',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
        'HTTP_X_FORWARDED_PROTO' => 'HTTPS',
    ]);
    AgentOfficePeer::resolve($req, ['10.0.0.0/24']);
}, 'Trusted denial: uppercase X-Forwarded-Proto');

$testsRun++;
assertThrowsPeerException(function () {
    $req = Request::create('http://upstream/api', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.5',
        'HTTP_X_FORWARDED_FOR' => '',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);
    AgentOfficePeer::resolve($req, ['10.0.0.0/24']);
}, 'Trusted denial: empty string X-Forwarded-For');

$testsRun++;
assertThrowsPeerException(function () {
    $req = Request::create('http://upstream/api', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.5',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);
    $req->headers->set('Forwarded', ['for=203.0.113.195', 'for=10.0.0.2']);
    AgentOfficePeer::resolve($req, ['10.0.0.0/24']);
}, 'Trusted denial: duplicate Forwarded headers');

$testsRun++;
assertThrowsPeerException(function () {
    $req = Request::create('http://upstream/api', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.5',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);
    $req->headers->set('X-Forwarded-Host', ['internal.host', 'other.host']);
    AgentOfficePeer::resolve($req, ['10.0.0.0/24']);
}, 'Trusted denial: duplicate X-Forwarded-Host headers');

// 7. Additional direct peer edge cases
$testsRun++;
$req7a = Request::create('https://example.com/api', 'GET', [], [], [], [
    'REMOTE_ADDR' => '198.51.100.10',
    'HTTPS' => 'on',
]);
$res7a = AgentOfficePeer::resolve($req7a, []);
assertPeerCheck($res7a === ['ip' => '198.51.100.10', 'secure' => true], 'Case 7a: Direct peer with empty trusted proxies list');

$testsRun++;
$req7b = Request::create('http://example.com/api', 'GET', [], [], [], [
    'REMOTE_ADDR' => '198.51.100.10',
    'HTTPS' => 'off',
]);
$res7b = AgentOfficePeer::resolve($req7b, ['10.0.0.0/24']);
assertPeerCheck($res7b === ['ip' => '198.51.100.10', 'secure' => false], 'Case 7b: Direct peer with HTTPS off');

$testsRun++;
$req7c = Request::create('http://example.com/api', 'GET', [], [], [], [
    'REMOTE_ADDR' => '2001:db8:85a3::8a2e:370:7334',
]);
$res7c = AgentOfficePeer::resolve($req7c, ['10.0.0.0/24']);
assertPeerCheck($res7c === ['ip' => '2001:db8:85a3::8a2e:370:7334', 'secure' => false], 'Case 7c: Direct IPv6 HTTP untrusted peer');

echo "PASS: All {$testsRun} AgentOfficePeer perimeter security checks succeeded.\n";
