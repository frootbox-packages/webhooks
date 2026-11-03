<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

/**
 * Checks that a host is on the internet (decision 0253): an IP must be
 * global, a name must resolve only to global IPs. The Sender connects to
 * the IP checked here, so DNS cannot change it in between.
 */
final class PublicAddress
{
    /**
     * @param (callable(string): list<string>)|null $resolve the IPs of a name, by default from DNS
     *
     * @return array{?string, ?string} the IP to connect to (null for an IP as host) and the problem, null if none
     */
    public static function check(string $host, ?callable $resolve = null): array
    {
        $host = strtolower(trim($host, '[]'));

        if (false !== filter_var($host, \FILTER_VALIDATE_IP)) {
            return [null, self::global($host) ? null : \sprintf('The address %s is not on the internet.', $host)];
        }

        $ips = ($resolve ?? self::dns(...))($host);

        if ([] === $ips) {
            return [null, \sprintf('The name %s cannot be resolved.', $host)];
        }

        foreach ($ips as $ip) {
            if (!self::global($ip)) {
                return [null, \sprintf('The name %s points to %s, which is not on the internet.', $host, $ip)];
            }
        }

        return [$ips[0], null];
    }

    public static function global(string $ip): bool
    {
        return false !== filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_GLOBAL_RANGE | \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * @return list<string>
     */
    private static function dns(string $host): array
    {
        $records = @dns_get_record($host, \DNS_A | \DNS_AAAA);
        $ips = [];

        foreach (\is_array($records) ? $records : [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (\is_string($ip)) {
                $ips[] = $ip;
            }
        }

        if ([] === $ips) {
            $found = @gethostbynamel($host);
            $ips = \is_array($found) ? $found : [];
        }

        return $ips;
    }
}
