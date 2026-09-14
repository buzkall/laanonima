<?php

namespace App\Support\PublisherLogos;

use App\Support\PublisherName;
use Illuminate\Support\Str;

/**
 * Which Wikidata item a publisher row is, and what it says about it.
 *
 * Two guards decide whether a candidate is the publisher, and both are needed:
 *
 * - its P31 has to be one of `publishers.logos.publisher_types`, because
 *   "Taurus" is a constellation, a missile and a rocket before it is an imprint;
 * - the text the search matched has to be the name that was searched, because
 *   the search is by prefix and a longer name that begins the same way is a
 *   different publisher.
 *
 * Losing a logotype to a guard is the right trade for never putting another
 * company's on the page. The `--qid` option on the command is how a publisher
 * the guards miss is named by hand.
 */
class WikidataPublisherSource
{
    public function __construct(private readonly WikimediaApi $api) {}

    /**
     * @param  string|null  $qid  a pinned item, which skips the search and the guards
     */
    public function find(string $name, ?string $qid = null): ?WikidataPublisher
    {
        $entity = filled($qid)
            ? $this->api->entities([$qid])[$qid] ?? null
            : $this->firstPublisher($name);

        return is_array($entity) ? $this->publisher($entity) : null;
    }

    /**
     * The spellings to search, from the name as filed to the barest one.
     *
     * The shop files "NORMA EDITORIAL, S.A." and Wikidata finds nothing for it,
     * so the legal form comes off; "Ediciones La Cúpula" is filed on Wikidata as
     * "La Cúpula", so a generic word comes off after that.
     *
     * @return list<string>
     */
    public function variants(string $name): array
    {
        $name = PublisherName::normalize($name);
        $bare = $this->withoutLegalForm($name);

        $variants = [];

        foreach ([$name, $bare, $this->withoutGenericWord($bare)] as $variant) {
            $slug = Str::slug($variant);

            if ($slug !== '' && ! array_key_exists($slug, $variants)) {
                $variants[$slug] = $variant;
            }
        }

        return array_values($variants);
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function firstPublisher(string $name): ?array
    {
        foreach ($this->variants($name) as $variant) {
            $candidates = array_values(array_filter(
                $this->api->search($variant),
                fn(array $candidate): bool => Str::slug($candidate['match']) === Str::slug($variant),
            ));

            if ($candidates === []) {
                continue;
            }

            $entities = $this->api->entities(array_column($candidates, 'id'));

            foreach ($candidates as $candidate) {
                $entity = $entities[$candidate['id']] ?? null;

                if (is_array($entity) && $this->isPublisher($entity)) {
                    return $entity;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $entity
     */
    private function isPublisher(array $entity): bool
    {
        return array_intersect(
            WikimediaApi::claims($entity, 'P31'),
            (array)config('publishers.logos.publisher_types'),
        ) !== [];
    }

    /**
     * @param  array<array-key, mixed>  $entity
     */
    private function publisher(array $entity): ?WikidataPublisher
    {
        $qid = $entity['id'] ?? null;

        /* A pinned id that does not exist comes back as `{"missing": ""}`. */
        if (! is_string($qid)) {
            return null;
        }

        $label = data_get($entity, 'labels.es.value') ?? data_get($entity, 'labels.en.value');
        $website = WikimediaApi::claims($entity, 'P856')[0] ?? null;
        $file = WikimediaApi::claims($entity, 'P154')[0] ?? null;

        $identified = new WikidataPublisher(
            qid: $qid,
            label: is_string($label) ? $label : null,
            website: $website,
        );

        if ($file === null) {
            return $identified;
        }

        $info = $this->api->imageInfo("File:{$file}");
        $thumb = $info['thumburl'] ?? null;

        /* Commons failing costs the logotype, not the identification: the
           website is still worth filling in and falling back to. */
        if (! is_array($info) || ! is_string($thumb) || blank($thumb)) {
            return $identified;
        }

        return new WikidataPublisher(
            qid: $qid,
            label: $identified->label,
            website: $website,
            file: "File:{$file}",
            imageUrl: WikimediaApi::withoutTracking($thumb),
            artist: WikimediaApi::plainText(data_get($info, 'extmetadata.Artist.value')),
            license: WikimediaApi::plainText(data_get($info, 'extmetadata.LicenseShortName.value')),
            licenseUrl: WikimediaApi::plainText(data_get($info, 'extmetadata.LicenseUrl.value')),
            sourceUrl: is_string($info['descriptionurl'] ?? null) ? $info['descriptionurl'] : null,
        );
    }

    /**
     * "Norma Editorial, S.A." is "Norma Editorial"; "Continta Me Tienes
     * (Errementari S.L.)" is "Continta Me Tienes".
     */
    private function withoutLegalForm(string $name): string
    {
        $words = preg_split('/[\s,]+/u', Str::squish((string)preg_replace('/\([^)]*\)/u', ' ', $name)), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $forms = (array)config('publishers.logos.legal_forms');

        while (count($words) > 1 && in_array(str_replace('.', '', mb_strtolower(end($words))), $forms, true)) {
            array_pop($words);
        }

        return implode(' ', $words);
    }

    /**
     * Drop one generic word from the start or the end, as long as a name is left.
     */
    private function withoutGenericWord(string $name): string
    {
        foreach ((array)config('publishers.logos.generic_words') as $word) {
            $quoted = preg_quote((string)$word, '/');

            foreach (["/^{$quoted}\\s+/iu", "/\\s+{$quoted}$/iu"] as $pattern) {
                $stripped = trim((string)preg_replace($pattern, '', $name));

                if ($stripped !== $name && $stripped !== '') {
                    return $stripped;
                }
            }
        }

        return $name;
    }
}
