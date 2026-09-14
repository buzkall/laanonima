<?php

namespace App\Support\PublisherLogos;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The three Wikimedia calls a publisher lookup makes, none of which ever throws.
 *
 * A sibling of the private helpers in WikidataPortraitSource rather than a
 * refactor of them: La Cupida's portraits are tuned and tested around that
 * class, and moving them onto this one is a change of its own.
 */
class WikimediaApi
{
    private const string WIKIDATA = 'https://www.wikidata.org/w/api.php';
    private const string COMMONS = 'https://commons.wikimedia.org/w/api.php';

    /**
     * Candidate items for a text, best first, with the text each one matched.
     *
     * The match is what the publisher guard compares: the search is by prefix,
     * so "Salamandra" also brings back whatever begins with it.
     *
     * @return list<array{id: string, match: string}>
     */
    public function search(string $text): array
    {
        $response = $this->get(self::WIKIDATA, [
            'action'   => 'wbsearchentities',
            'format'   => 'json',
            'language' => 'es',
            'uselang'  => 'es',
            'type'     => 'item',
            'limit'    => (int)config('publishers.logos.search_limit'),
            'search'   => $text,
        ], ['search' => $text]);

        $candidates = [];

        foreach (Arr::wrap(data_get($response, 'search')) as $result) {
            $id = data_get($result, 'id');
            $match = data_get($result, 'match.text') ?? data_get($result, 'label');

            if (is_string($id) && is_string($match)) {
                $candidates[] = ['id' => $id, 'match' => $match];
            }
        }

        return $candidates;
    }

    /**
     * All the candidates in one request rather than one request each.
     *
     * @param  list<string>  $qids
     * @return array<string, mixed>
     */
    public function entities(array $qids): array
    {
        if ($qids === []) {
            return [];
        }

        $response = $this->get(self::WIKIDATA, [
            'action'    => 'wbgetentities',
            'format'    => 'json',
            'props'     => 'claims|labels',
            'languages' => 'es|en',
            'ids'       => implode('|', $qids),
        ], ['ids' => $qids]);

        $entities = data_get($response, 'entities');

        return is_array($entities) ? $entities : [];
    }

    /**
     * The `imageinfo` block Commons keeps for a file, thumbnail URL included.
     *
     * @return array<string, mixed>|null
     */
    public function imageInfo(string $file): ?array
    {
        $response = $this->get(self::COMMONS, [
            'action'     => 'query',
            'format'     => 'json',
            'prop'       => 'imageinfo',
            'iiprop'     => 'url|extmetadata',
            'iiurlwidth' => (int)config('publishers.logos.thumb_width'),
            'titles'     => $file,
        ], ['file' => $file]);

        $pages = data_get($response, 'query.pages');

        if (! is_array($pages) || $pages === []) {
            return null;
        }

        $info = data_get(Arr::first($pages), 'imageinfo.0');

        return is_array($info) ? $info : null;
    }

    /**
     * The values of one property on an entity: the item id for a claim that
     * points at another item, the string itself for a file name or a URL.
     *
     * Preferred statements come first and deprecated ones not at all -- a
     * publisher that changed its logotype keeps the old one on the item,
     * marked as such.
     *
     * @param  array<array-key, mixed>  $entity
     * @return list<string>
     */
    public static function claims(array $entity, string $property): array
    {
        $preferred = [];
        $normal = [];

        foreach (Arr::wrap(data_get($entity, "claims.{$property}")) as $statement) {
            $rank = data_get($statement, 'rank', 'normal');
            $value = data_get($statement, 'mainsnak.datavalue.value');
            $value = is_array($value) ? ($value['id'] ?? null) : $value;

            if ($rank === 'deprecated' || ! is_string($value) || blank($value)) {
                continue;
            }

            if ($rank === 'preferred') {
                $preferred[] = $value;
            } else {
                $normal[] = $value;
            }
        }

        return [...$preferred, ...$normal];
    }

    /**
     * Commons hangs utm_* parameters off the thumbnail URL it returns.
     */
    public static function withoutTracking(string $url): string
    {
        return (string)preg_replace('/\?.*$/', '', $url);
    }

    /**
     * `extmetadata` values are HTML fragments; the text is what is stored.
     */
    public static function plainText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5));

        return blank($text) ? null : $text;
    }

    /**
     * One GET that answers with an array, or null for every kind of failure.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $context  what to log if it goes wrong
     * @return array<array-key, mixed>|null
     */
    private function get(string $endpoint, array $query, array $context): ?array
    {
        try {
            $response = $this->request()->get($endpoint, $query);
        } catch (Throwable $exception) {
            Log::warning('Publisher lookup failed.', [...$context, 'exception' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Publisher lookup answered with an error.', [...$context, 'status' => $response->status()]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    private function request(): PendingRequest
    {
        return Http::withUserAgent((string)config('publishers.logos.user_agent'))
            ->timeout((int)config('publishers.logos.timeout'))
            ->retry(1, 200, throw: false);
    }
}
