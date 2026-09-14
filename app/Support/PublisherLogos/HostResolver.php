<?php

namespace App\Support\PublisherLogos;

use Throwable;

/**
 * The addresses a host name resolves to.
 *
 * Its own class so a test can say what a name resolves to without a network,
 * which is the whole of what the SSRF guard in PublicUrl is about.
 */
class HostResolver
{
    /**
     * @return list<string> IPv4 and IPv6 addresses, empty when the name does not resolve
     */
    public function resolve(string $host): array
    {
        try {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        } catch (Throwable) {
            $records = false;
        }

        $addresses = [];

        foreach (is_array($records) ? $records : [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        /* dns_get_record skips /etc/hosts, which is exactly where a name
           pointed at this machine would be written. */
        if ($addresses === []) {
            $addresses = @gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($addresses));
    }
}
