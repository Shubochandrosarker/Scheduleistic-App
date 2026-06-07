<?php

namespace App\Support;

/**
 * Guards server-side fetches against SSRF: rejects non-HTTP(S) URLs and any
 * host that points at a private, loopback, link-local, or reserved IP range
 * (e.g. cloud metadata at 169.254.169.254, localhost, RFC1918 networks).
 */
class SsrfGuard
{
    /** Hostnames that must never be fetched, regardless of DNS. */
    private const BLOCKED_HOSTS = ['localhost', 'metadata.google.internal'];

    /**
     * Best-effort synchronous check usable in validation. Blocks bad schemes,
     * obviously-internal hostnames, and literal private/reserved IP hosts.
     */
    public function isAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! $parts || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        if (in_array($host, self::BLOCKED_HOSTS, true) || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        // If the host is a literal IP, validate it directly.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        return true;
    }

    /**
     * Stricter check used at fetch time: resolves the host and ensures every
     * resolved address is a public IP. Returns false if resolution fails.
     */
    public function isFetchable(string $url): bool
    {
        if (! $this->isAllowed($url)) {
            return false;
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST));

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        $ips = $this->resolve($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    public function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    /** @return array<int, string> */
    private function resolve(string $host): array
    {
        $ips = [];

        foreach (['A' => DNS_A, 'AAAA' => DNS_AAAA] as $key => $type) {
            $records = @dns_get_record($host, $type) ?: [];
            foreach ($records as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if ($ip) {
                    $ips[] = $ip;
                }
            }
        }

        return $ips;
    }
}
