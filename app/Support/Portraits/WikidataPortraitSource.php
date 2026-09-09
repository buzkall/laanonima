<?php

namespace App\Support\Portraits;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wikidata says who a writer is; Commons hands over their photo and its license.
 *
 * Two guards decide whether a candidate is the right person, and the second one
 * is the whole reason this class is not four lines long. Searching "Mary Oliver"
 * returns a Dutch jazz singer before the poet, and "Michael McDowell" returns an
 * Irish politician before the novelist. Requiring P31=Q5 *and* a writing
 * occupation in P106 fixes both, and it is why "Vv. Aa." and the pseudonym
 * "Beka" resolve to nothing at all rather than to somebody's face.
 *
 * Measured over the shop's whole 150-name deck pool: 118 photos. Over a 19-name
 * sub-sample checked by hand: without the occupation guard, thirteen photos of
 * which two were the wrong person; with it, eleven and none wrong. Losing two
 * faces to keep two strangers off the cards is the right trade -- do not relax
 * it to raise the number.
 */
class WikidataPortraitSource implements PortraitSource
{
    private const string WIKIDATA = 'https://www.wikidata.org/w/api.php';
    private const string COMMONS = 'https://commons.wikimedia.org/w/api.php';

    public function find(string $name, ?string $qid = null, ?string $file = null): ?PortraitMatch
    {
        /* A pinned file answers on its own: somebody looked at the face this
           class chose, said no, and named the one they wanted instead. */
        if (filled($file)) {
            return $this->pinnedFile($file);
        }

        $entity = filled($qid)
            ? $this->entities([$qid])[$qid] ?? null
            : $this->firstWriter($this->search($name));

        if (! is_array($entity)) {
            return null;
        }

        return $this->match($entity);
    }

    /**
     * Candidate items for a name, best first.
     *
     * Searched in Spanish because the shop is Spanish and its readers are: it
     * is what puts the Spanish label first and what finds a writer whose only
     * label is Spanish.
     *
     * @return array<int, string>
     */
    private function search(string $name): array
    {
        $response = $this->get(self::WIKIDATA, [
            'action'   => 'wbsearchentities',
            'format'   => 'json',
            'language' => 'es',
            'uselang'  => 'es',
            'type'     => 'item',
            'limit'    => (int)config('cupida.portraits.search_limit'),
            'search'   => $name,
        ], ['name' => $name]);

        /** @var array<int, string> $ids */
        $ids = array_values(array_filter(
            Arr::wrap(data_get($response, 'search.*.id')),
            is_string(...),
        ));

        return $ids;
    }

    /**
     * All the candidates in one request rather than one request each.
     *
     * @param  array<int, string>  $qids
     * @return array<string, array<string, mixed>>
     */
    private function entities(array $qids): array
    {
        if ($qids === []) {
            return [];
        }

        $response = $this->get(self::WIKIDATA, [
            'action'    => 'wbgetentities',
            'format'    => 'json',
            'props'     => 'claims|labels|descriptions',
            'languages' => 'es|en',
            'ids'       => implode('|', $qids),
        ], ['ids' => $qids]);

        /** @var array<string, array<string, mixed>> $entities */
        $entities = is_array(data_get($response, 'entities')) ? data_get($response, 'entities') : [];

        return $entities;
    }

    /**
     * The first candidate, in the search's own order, that is a human who writes.
     *
     * @param  array<int, string>  $qids
     * @return array<string, mixed>|null
     */
    private function firstWriter(array $qids): ?array
    {
        $entities = $this->entities($qids);

        foreach ($qids as $qid) {
            $entity = $entities[$qid] ?? null;

            if (! is_array($entity)) {
                continue;
            }

            if (! $this->isHuman($entity) || $this->occupations($entity) === []) {
                continue;
            }

            return $entity;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function isHuman(array $entity): bool
    {
        return in_array(
            (string)config('cupida.portraits.human'),
            $this->claimIds($entity, 'P31'),
            true,
        );
    }

    /**
     * The writing occupations this item claims, which is empty for anyone who
     * does not write -- a footballer, a politician, a jazz singer.
     *
     * @param  array<string, mixed>  $entity
     * @return array<int, string>
     */
    private function occupations(array $entity): array
    {
        return array_values(array_intersect(
            $this->claimIds($entity, 'P106'),
            (array)config('cupida.portraits.occupations'),
        ));
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<int, string>
     */
    private function claimIds(array $entity, string $property): array
    {
        return array_values(array_filter(
            Arr::wrap(data_get($entity, "claims.{$property}.*.mainsnak.datavalue.value.id")),
            is_string(...),
        ));
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function match(array $entity): PortraitMatch
    {
        $qid = is_string($entity['id'] ?? null) ? $entity['id'] : null;
        $label = data_get($entity, 'labels.es.value') ?? data_get($entity, 'labels.en.value');
        $description = data_get($entity, 'descriptions.es.value') ?? data_get($entity, 'descriptions.en.value');

        $file = data_get($entity, 'claims.P18.0.mainsnak.datavalue.value');
        $file = is_string($file) ? "File:{$file}" : null;

        $identified = new PortraitMatch(
            qid: $qid,
            label: is_string($label) ? $label : null,
            description: is_string($description) ? $description : null,
            occupations: $this->occupations($entity),
        );

        /* The right person, and nobody has photographed them. That is a
           different verdict from "we could not tell who this is", so it comes
           back as a match with no file rather than as null. */
        if ($file === null) {
            return $identified;
        }

        return $this->withImage($identified, $file) ?? $identified;
    }

    /**
     * A Commons file named by hand, with no Wikidata item behind it.
     */
    private function pinnedFile(string $file): ?PortraitMatch
    {
        return $this->withImage(new PortraitMatch(qid: null, label: null, description: null), $file);
    }

    /**
     * Hang the Commons thumbnail and its license off a match.
     */
    private function withImage(PortraitMatch $match, string $file): ?PortraitMatch
    {
        $response = $this->get(self::COMMONS, [
            'action'     => 'query',
            'format'     => 'json',
            'prop'       => 'imageinfo',
            'iiprop'     => 'url|extmetadata|size',
            'iiurlwidth' => (int)config('cupida.portraits.thumb_width'),
            'titles'     => $file,
        ], ['file' => $file]);

        $pages = data_get($response, 'query.pages');

        if (! is_array($pages) || $pages === []) {
            return null;
        }

        $info = data_get(Arr::first($pages), 'imageinfo.0');

        if (! is_array($info)) {
            return null;
        }

        $thumb = $info['thumburl'] ?? null;

        if (! is_string($thumb) || blank($thumb)) {
            return null;
        }

        return new PortraitMatch(
            qid: $match->qid,
            label: $match->label,
            description: $match->description,
            occupations: $match->occupations,
            file: $file,
            imageUrl: $this->withoutTracking($thumb),
            artist: $this->plainText(data_get($info, 'extmetadata.Artist.value')),
            license: $this->plainText(data_get($info, 'extmetadata.LicenseShortName.value')),
            licenseUrl: $this->plainText(data_get($info, 'extmetadata.LicenseUrl.value')),
            attributionRequired: filter_var(
                data_get($info, 'extmetadata.AttributionRequired.value'),
                FILTER_VALIDATE_BOOL,
            ),
            /* The file page a reader can actually visit, not the thumbnail. */
            sourceUrl: is_string($info['descriptionurl'] ?? null) ? $info['descriptionurl'] : null,
        );
    }

    /**
     * Commons decorates the thumbnail URL it returns with utm_* parameters.
     *
     * They are harmless to fetch and wrong to record: this URL is committed and
     * re-fetched on every deploy for years, so it should name the file and
     * nothing else.
     */
    private function withoutTracking(string $url): string
    {
        return (string)preg_replace('/\?.*$/', '', $url);
    }

    /**
     * `extmetadata` values are HTML fragments -- the artist arrives as an
     * anchor tag. The page prints the text, so the text is what is stored.
     */
    private function plainText(mixed $value): ?string
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
     * @return array<string, mixed>|null
     */
    private function get(string $endpoint, array $query, array $context): ?array
    {
        try {
            $response = $this->request()->get($endpoint, $query);
        } catch (Throwable $exception) {
            Log::warning('Portrait lookup failed.', [...$context, 'exception' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Portrait lookup answered with an error.', [...$context, 'status' => $response->status()]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    private function request(): PendingRequest
    {
        return Http::withUserAgent(config('cupida.portraits.user_agent'))
            ->timeout(config('cupida.portraits.timeout'))
            ->retry(2, 200, throw: false);
    }
}
