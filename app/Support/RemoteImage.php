<?php

namespace App\Support;

use Illuminate\Support\Str;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * The guard around fetching an image from a URL we did not write.
 *
 * Every image this application pulls server-side comes out of somebody else's
 * response -- a metadata provider's cover link, a Commons thumbnail -- so it is
 * untrusted input pointed at our own network. Two rules, and both of them have
 * to hold for every host list: https only, and only hosts we chose.
 *
 * Shared rather than copied because the second rule is the one that is easy to
 * get subtly wrong in one place and right in the other, and a host list is the
 * only thing that legitimately differs between callers.
 */
final class RemoteImage
{
    /**
     * Is this a URL we are willing to fetch server-side?
     *
     * @param  array<int, string>  $hosts  `Str::is` patterns, so `*.archive.org` works
     */
    public static function allowed(string $url, array $hosts): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? null) !== 'https') {
            return false;
        }

        $host = $parts['host'] ?? null;

        if (! is_string($host) || blank($host)) {
            return false;
        }

        return Str::is($hosts, $host);
    }

    /**
     * Guzzle options that refuse a redirect off the list.
     *
     * An allowed host is still free to send us somewhere else, so the check runs
     * again on every hop rather than once on the URL we were handed. Throwing is
     * the only way out of `on_redirect`; callers catch it as an ordinary failure.
     *
     * @param  array<int, string>  $hosts
     * @return array<string, mixed>
     */
    public static function redirectGuard(array $hosts): array
    {
        return [
            'allow_redirects' => [
                'max'         => 5,
                'protocols'   => ['https'],
                'strict'      => true,
                'referer'     => false,
                'on_redirect' => function(mixed $request, mixed $response, UriInterface $uri) use ($hosts): void {
                    if (! self::allowed((string)$uri, $hosts)) {
                        throw new RuntimeException("Image redirect refused: {$uri}");
                    }
                },
            ],
        ];
    }
}
