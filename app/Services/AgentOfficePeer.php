<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;

class AgentOfficePeer
{
    /**
     * @param  array<string>  $trustedProxies
     * @return array{ip: string, secure: bool}
     */
    public static function resolve(Request $request, array $trustedProxies): array
    {
        if (! array_is_list($trustedProxies)) {
            throw new RuntimeException('invalid_trusted_proxies');
        }

        foreach ($trustedProxies as $cidr) {
            if (! is_string($cidr) || ! AgentOfficeSecurity::validCidr($cidr)) {
                throw new RuntimeException('invalid_trusted_proxies');
            }
        }

        $rawRemote = $request->server->get('REMOTE_ADDR');
        if (! is_string($rawRemote) || ! self::isCanonicalIp($rawRemote)) {
            throw new RuntimeException('invalid_remote_addr');
        }

        // ponytail: single reverse proxy hop only; upgrade to chained proxy traversal if intermediate proxies introduced.
        if ($trustedProxies === [] || ! IpUtils::checkIp($rawRemote, $trustedProxies)) {
            $rawHttps = $request->server->get('HTTPS');
            $secure = is_string($rawHttps)
                ? in_array(strtolower($rawHttps), ['on', '1'], true)
                : ($rawHttps === 1 || $rawHttps === true);

            return [
                'ip' => $rawRemote,
                'secure' => $secure,
            ];
        }

        if (
            $request->headers->has('forwarded')
            || $request->headers->has('x-forwarded-host')
            || $request->headers->has('x-forwarded-port')
            || $request->headers->has('x-forwarded-prefix')
        ) {
            throw new RuntimeException('forbidden_forward_header');
        }

        $xffList = $request->headers->all('x-forwarded-for');
        if (count($xffList) !== 1 || ! is_string($xffList[0]) || ! self::isCanonicalIp($xffList[0])) {
            throw new RuntimeException('invalid_xff');
        }

        $xfpList = $request->headers->all('x-forwarded-proto');
        if (count($xfpList) !== 1 || $xfpList[0] !== 'https') {
            throw new RuntimeException('invalid_xfp');
        }

        return [
            'ip' => $xffList[0],
            'secure' => true,
        ];
    }

    private static function isCanonicalIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $bytes = @inet_pton($ip);
        if ($bytes === false) {
            return false;
        }

        return inet_ntop($bytes) === $ip;
    }
}
