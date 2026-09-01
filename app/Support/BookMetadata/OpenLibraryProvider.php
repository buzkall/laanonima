<?php

namespace App\Support\BookMetadata;

use App\Enums\BookLanguage;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Open Library needs no credentials, which is why it leads the chain.
 *
 * Coverage of Spanish titles is good but not complete: of eleven real Spanish
 * ISBNs sampled while designing this, nine resolved with metadata and a cover.
 * Misses are ordinary, not errors.
 */
class OpenLibraryProvider implements BookMetadataProvider
{
    private const string ENDPOINT = 'https://openlibrary.org/api/books';
    private const string EDITION_ENDPOINT = 'https://openlibrary.org/isbn';
    private const string COVERS_ENDPOINT = 'https://covers.openlibrary.org/b/isbn';

    public function find(string $isbn13): ?BookMetadata
    {
        try {
            $response = $this->request()->get(self::ENDPOINT, [
                'bibkeys' => "ISBN:{$isbn13}",
                'format'  => 'json',
                'jscmd'   => 'data',
            ]);
        } catch (Throwable $exception) {
            Log::warning('Open Library lookup failed.', ['isbn13' => $isbn13, 'exception' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed>|null $data */
        $data = $response->json("ISBN:{$isbn13}");

        if (! is_array($data) || $data === []) {
            return null;
        }

        $publishDate = is_string($data['publish_date'] ?? null) ? $data['publish_date'] : null;
        $physical = $this->physical($isbn13);

        return new BookMetadata(
            isbn13: $isbn13,
            isbn10: Arr::first(Arr::wrap(data_get($data, 'identifiers.isbn_10'))),
            title: is_string($data['title'] ?? null) ? $data['title'] : null,
            subtitle: is_string($data['subtitle'] ?? null) ? $data['subtitle'] : null,
            contributors: $this->contributors($data),
            publisherName: Arr::first(Arr::wrap(data_get($data, 'publishers.*.name'))),
            publishedOn: $this->publishedOn($publishDate),
            publishedYear: $this->publishedYear($publishDate),
            legalDeposit: Arr::first(Arr::wrap(data_get($data, 'identifiers.depósito_legal'))),
            cityOfPublication: Arr::first(Arr::wrap(data_get($data, 'publish_places.*.name'))),
            pages: is_int($data['number_of_pages'] ?? null) ? $data['number_of_pages'] : null,
            heightMm: $physical['height'],
            widthMm: $physical['width'],
            thicknessMm: $physical['thickness'],
            weightGrams: $physical['weight'],
            language: BookLanguage::Spa,
            /*
             | Open Library's "subjects" are reader-contributed tags, not a
             | classification: a single record yields "Girls", "Time",
             | "tortoises", "lilies", "interest". Fifteen rows of that in the
             | Materias table is worse than none, so nothing is imported here.
             | Google Books' BISAC-style categories are kept; they are real.
             */
            subjects: [],
            synopsis: $this->synopsis($data),
            coverSourceUrl: $this->coverUrl($data, $isbn13),
            source: 'open_library',
            raw: $data,
        );
    }

    /**
     * How big the book actually is, off the edition record.
     *
     * This is a second request, because the measurements are simply not in the
     * jscmd=data view the rest of this class reads: "physical_dimensions" and
     * "weight" only exist on the edition document. It is worth the round trip
     * -- a shelf drawn to scale needs them and Google Books is inert without a
     * key -- and FetchBookMetadata caches the merged result for a day, so it is
     * one extra call per ISBN rather than one per lookup.
     *
     * A miss here never fails the lookup: most records have no measurements at
     * all, which is an ordinary outcome rather than an error.
     *
     * @return array{height: int|null, width: int|null, thickness: int|null, weight: int|null}
     */
    private function physical(string $isbn13): array
    {
        $empty = ['height' => null, 'width' => null, 'thickness' => null, 'weight' => null];

        try {
            $response = $this->request()->get(self::EDITION_ENDPOINT . "/{$isbn13}.json");
        } catch (Throwable $exception) {
            Log::debug('Open Library edition lookup failed.', ['isbn13' => $isbn13, 'exception' => $exception->getMessage()]);

            return $empty;
        }

        if (! $response->successful()) {
            return $empty;
        }

        $dimensions = PhysicalMeasure::dimensionsInMm($response->json('physical_dimensions'));

        return [
            'height'    => $dimensions['height'] ?? null,
            'width'     => $dimensions['width'] ?? null,
            'thickness' => $dimensions['thickness'] ?? null,
            'weight'    => PhysicalMeasure::weightInGrams($response->json('weight')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{name: string, role: string}>
     */
    private function contributors(array $data): array
    {
        $names = array_filter(Arr::wrap(data_get($data, 'authors.*.name')), is_string(...));

        return array_values(array_map(
            fn(string $name): array => ['name' => $name, 'role' => 'author'],
            $names,
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function synopsis(array $data): ?string
    {
        $description = $data['description'] ?? null;

        if (is_array($description)) {
            $description = $description['value'] ?? null;
        }

        return is_string($description) && filled($description) ? $description : null;
    }

    /**
     * Open Library gives a cover object when it has one, but not always: some
     * records omit it while covers.openlibrary.org still serves the image.
     *
     * The default=false query string matters. Without it a missing cover comes
     * back as a 200 with a grey placeholder, which we would happily store.
     *
     * @param  array<string, mixed>  $data
     */
    private function coverUrl(array $data, string $isbn13): ?string
    {
        $declared = data_get($data, 'cover.large') ?? data_get($data, 'cover.medium');

        if (is_string($declared) && filled($declared)) {
            return $declared;
        }

        $probe = self::COVERS_ENDPOINT . "/{$isbn13}-L.jpg?default=false";

        try {
            if ($this->request()->head($probe)->successful()) {
                return $probe;
            }
        } catch (Throwable $exception) {
            Log::debug('Open Library cover probe failed.', ['isbn13' => $isbn13, 'exception' => $exception->getMessage()]);
        }

        return null;
    }

    /**
     * publish_date is free text: "2002" and "May 29, 2019" both occur.
     */
    private function publishedOn(?string $publishDate): ?string
    {
        if ($publishDate === null || preg_match('/^\d{4}$/', $publishDate)) {
            return null;
        }

        try {
            return Carbon::parse($publishDate)->toDateString();
        } catch (InvalidFormatException) {
            return null;
        }
    }

    private function publishedYear(?string $publishDate): ?int
    {
        if ($publishDate === null || ! preg_match('/(\d{4})/', $publishDate, $matches)) {
            return null;
        }

        return (int)$matches[1];
    }

    private function request(): PendingRequest
    {
        return Http::withUserAgent(config('books.metadata.user_agent'))
            ->timeout(config('books.metadata.timeout'))
            ->retry(2, 200, throw: false);
    }
}
