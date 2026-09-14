<?php

namespace App\Support\PublisherLogos;

use Illuminate\Support\Str;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * The guard around fetching a URL on a host nobody chose.
 *
 * RemoteImage answers "is this one of our hosts?", which is the right question
 * for a Commons thumbnail and no use at all for a publisher's website: the host
 * is whatever somebody typed into the panel or Wikidata recorded. So the rule
 * here is about where the request lands instead -- https, the default port, and
 * an address on the public internet, checked for every address the name
 * resolves to and again on every redirect hop.
 *
 * The first hop is pinned to the address that was checked (CURLOPT_RESOLVE), so
 * a name that resolves to a public address for the check and a private one for
 * the request gets the public one. Redirect hops are re-checked but not pinned.
 */
class PublicUrl
{
    /**
     * Names that only ever mean a machine on our side of the network.
     */
    private const array LOCAL_SUFFIXES = ['.localhost', '.local', '.internal', '.lan', '.home.arpa'];

    public function __construct(private readonly HostResolver $resolver) {}

    public function allowed(string $url): bool
    {
        return $this->addresses($url) !== [];
    }

    /**
     * Guzzle options for fetching this URL, or null when it may not be fetched.
     *
     * @return array<string, mixed>|null
     */
    public function options(string $url): ?array
    {
        $addresses = $this->addresses($url);

        if ($addresses === []) {
            return null;
        }

        $options = [
            'allow_redirects' => [
                'max'         => 3,
                'protocols'   => ['https'],
                'strict'      => true,
                'referer'     => false,
                'on_redirect' => function(mixed $request, mixed $response, UriInterface $uri): void {
                    if (! $this->allowed((string)$uri)) {
                        throw new RuntimeException("Redirect refused: {$uri}");
                    }
                },
            ],
        ];

        $host = (string)parse_url($url, PHP_URL_HOST);

        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) === false) {
            $address = $addresses[0];
            $options['curl'] = [
                CURLOPT_RESOLVE => [sprintf('%s:443:%s', $host, str_contains($address, ':') ? "[{$address}]" : $address)],
            ];
        }

        return $options;
    }

    /**
     * Every address the URL's host stands for, or none when any of them is not public.
     *
     * @return list<string>
     */
    private function addresses(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? null) !== 'https') {
            return [];
        }

        if (isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            return [];
        }

        $host = rtrim(mb_strtolower($parts['host'] ?? ''), '.');

        if ($host === '' || $host === 'localhost' || Str::endsWith($host, self::LOCAL_SUFFIXES)) {
            return [];
        }

        $literal = trim($host, '[]');

        $addresses = filter_var($literal, FILTER_VALIDATE_IP) !== false
            ? [$literal]
            : $this->resolver->resolve($host);

        if ($addresses === [] || ! array_all($addresses, $this->isPublic(...))) {
            return [];
        }

        return $addresses;
    }

    private function isPublic(string $address): bool
    {
        /* ::ffff:127.0.0.1 is loopback written as IPv6. */
        if (str_starts_with(mb_strtolower($address), '::ffff:') && filter_var(substr($address, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $address = substr($address, 7);
        }

        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE,
        ) !== false;
    }
}
