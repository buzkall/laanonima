<?php

namespace App\Support\PublisherLogos;

use Dom\Element;
use Dom\HTMLDocument;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The images a publisher's own home page says stand for it, best first.
 *
 * In order of how likely each is to be the wordmark rather than a square:
 *
 * 1. a JSON-LD Organization `logo` (WordPress SEO plugins write the real one
 *    here), `og:logo` or `itemprop="logo"`;
 * 2. the largest `apple-touch-icon`, which is at least 180px;
 * 3. the largest `rel="icon"`;
 * 4. and, only when the page declares nothing, `/apple-touch-icon.png`.
 *
 * `og:image` is never taken: on real sites it is the share banner -- Blackie
 * Books' is 4007x2001 -- not the logotype. SVG and ICO are skipped too, because
 * nothing on this server can decode either.
 */
class WebsiteIconSource
{
    public function __construct(private readonly PublicUrl $publicUrl) {}

    /**
     * @return list<string> absolute https URLs, at most `website.max_candidates`
     */
    public function candidates(string $website): array
    {
        $home = $this->home($website);

        if ($home === null) {
            return [];
        }

        $options = $this->publicUrl->options($home);

        if ($options === null) {
            Log::warning('Publisher website refused: not a public https host.', ['website' => $website]);

            return [];
        }

        try {
            $response = Http::withUserAgent((string)config('publishers.logos.user_agent'))
                ->timeout((int)config('publishers.logos.website.timeout'))
                ->withOptions($options)
                ->get($home);
        } catch (Throwable $exception) {
            Log::warning('Publisher website could not be read.', ['website' => $website, 'exception' => $exception->getMessage()]);

            return [];
        }

        $type = $response->header('Content-Type');
        $html = $response->body();

        if (! $response->successful() || ($type !== '' && ! str_contains(mb_strtolower($type), 'text/html'))) {
            return [];
        }

        if ($html === '' || strlen($html) > (int)config('publishers.logos.website.max_html_bytes')) {
            return [];
        }

        return $this->rank($html, (string)($response->effectiveUri() ?? $home));
    }

    /**
     * The site's home page over https, whatever path or scheme was recorded.
     *
     * Wikidata files most websites as http://, and a website filed under a
     * publishing group's host is the group's, not the imprint's.
     */
    private function home(string $website): ?string
    {
        $website = trim($website);
        $host = parse_url(str_contains($website, '://') ? $website : "https://{$website}", PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = mb_strtolower($host);

        if (Str::is((array)config('publishers.logos.website.shared_hosts'), $host)) {
            return null;
        }

        return "https://{$host}/";
    }

    /**
     * @return list<string>
     */
    private function rank(string $html, string $pageUrl): array
    {
        try {
            $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        } catch (Throwable) {
            return [];
        }

        $base = $pageUrl;
        $declaredBase = $document->querySelector('base[href]')?->getAttribute('href');

        if (filled($declaredBase)) {
            $base = $this->resolve($pageUrl, $declaredBase) ?? $pageUrl;
        }

        /** @var list<array{href: string, tier: int, size: int}> $found */
        $found = [];

        foreach ($document->querySelectorAll('script[type="application/ld+json"]') as $script) {
            foreach ($this->jsonLdLogos((string)$script->textContent) as $href) {
                $found[] = ['href' => $href, 'tier' => 1, 'size' => 0];
            }
        }

        foreach ($document->querySelectorAll('meta[property="og:logo"], [itemprop="logo"]') as $element) {
            $href = $element->getAttribute('content') ?? $element->getAttribute('src') ?? $element->getAttribute('href');

            if (filled($href)) {
                $found[] = ['href' => $href, 'tier' => 1, 'size' => 0];
            }
        }

        foreach ($document->querySelectorAll('link[rel][href]') as $link) {
            $icon = $this->icon($link);

            if ($icon !== null) {
                $found[] = $icon;
            }
        }

        if ($found === []) {
            $found[] = ['href' => '/apple-touch-icon.png', 'tier' => 4, 'size' => 0];
        }

        usort($found, fn(array $a, array $b): int => [$a['tier'], $b['size']] <=> [$b['tier'], $a['size']]);

        $urls = array_filter(
            array_map(fn(array $candidate): ?string => $this->resolve($base, $candidate['href']), $found),
            fn(?string $url): bool => $url !== null && ! $this->undecodable($url),
        );

        return array_slice(array_unique($urls), 0, (int)config('publishers.logos.website.max_candidates'));
    }

    /**
     * @return array{href: string, tier: int, size: int}|null
     */
    private function icon(Element $link): ?array
    {
        $rels = preg_split('/\s+/', mb_strtolower((string)$link->getAttribute('rel')), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $href = (string)$link->getAttribute('href');

        $tier = match (true) {
            array_intersect(['apple-touch-icon', 'apple-touch-icon-precomposed'], $rels) !== [] => 2,
            in_array('icon', $rels, true)                                                       => 3,
            default                                                                             => null,
        };

        $type = mb_strtolower((string)$link->getAttribute('type'));
        $media = mb_strtolower((string)$link->getAttribute('media'));
        $sizes = mb_strtolower(trim((string)$link->getAttribute('sizes')));

        if ($tier === null || $href === '' || $sizes === 'any' || str_contains($media, 'dark')) {
            return null;
        }

        if (in_array($type, ['image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'], true)) {
            return null;
        }

        preg_match_all('/(\d+)x\d+/', $sizes, $declared);
        $size = $declared[1] === [] ? ($tier === 2 ? 180 : 0) : max(array_map(intval(...), $declared[1]));

        return ['href' => $href, 'tier' => $tier, 'size' => $size];
    }

    /**
     * The `logo` of every organization a JSON-LD block describes.
     *
     * @return list<string>
     */
    private function jsonLdLogos(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return [];
        }

        $nodes = isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : (array_is_list($data) ? $data : [$data]);
        $logos = [];

        foreach ($nodes as $node) {
            $types = array_map(strval(...), Arr::wrap(data_get($node, '@type')));

            if (array_intersect($types, ['Organization', 'Corporation', 'Publisher', 'NewsMediaOrganization']) === []) {
                continue;
            }

            $logo = data_get($node, 'logo');
            $logo = is_array($logo) ? ($logo['url'] ?? $logo['contentUrl'] ?? null) : $logo;

            if (is_string($logo) && filled($logo)) {
                $logos[] = $logo;
            }
        }

        return $logos;
    }

    private function resolve(string $base, string $href): ?string
    {
        try {
            $url = (string)UriResolver::resolve(Utils::uriFor($base), Utils::uriFor(trim($href)));
        } catch (Throwable) {
            return null;
        }

        return str_starts_with($url, 'https://') ? $url : null;
    }

    private function undecodable(string $url): bool
    {
        return in_array(mb_strtolower(pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)), ['svg', 'ico'], true);
    }
}
