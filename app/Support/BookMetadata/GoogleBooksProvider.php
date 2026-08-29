<?php

namespace App\Support\BookMetadata;

use App\Enums\BookLanguage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Google Books, used to fill the gaps Open Library leaves.
 *
 * Deliberately inert without an API key. Unauthenticated traffic shares a
 * pooled per-project quota that is routinely exhausted: a keyless call returns
 * 429 RESOURCE_EXHAUSTED with quota_limit_value "0", so there is nothing to be
 * gained by trying. Set GOOGLE_BOOKS_API_KEY and this starts contributing.
 */
class GoogleBooksProvider implements BookMetadataProvider
{
    private const string ENDPOINT = 'https://www.googleapis.com/books/v1/volumes';

    public function find(string $isbn13): ?BookMetadata
    {
        $key = config('books.metadata.google_books.key');

        if (blank($key)) {
            return null;
        }

        try {
            $response = $this->request()->get(self::ENDPOINT, [
                'q'       => "isbn:{$isbn13}",
                'country' => config('books.metadata.google_books.country'),
                'key'     => $key,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Google Books lookup failed.', ['isbn13' => $isbn13, 'exception' => $exception->getMessage()]);

            return null;
        }

        if ($response->status() === 429) {
            Log::warning('Google Books quota exhausted.', ['isbn13' => $isbn13]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed>|null $volume */
        $volume = $response->json('items.0.volumeInfo');

        if (! is_array($volume) || $volume === []) {
            return null;
        }

        $publishedDate = is_string($volume['publishedDate'] ?? null) ? $volume['publishedDate'] : null;

        return new BookMetadata(
            isbn13: $isbn13,
            isbn10: $this->industryIdentifier($volume, 'ISBN_10'),
            title: is_string($volume['title'] ?? null) ? $volume['title'] : null,
            subtitle: is_string($volume['subtitle'] ?? null) ? $volume['subtitle'] : null,
            contributors: $this->contributors($volume),
            publisherName: is_string($volume['publisher'] ?? null) ? $volume['publisher'] : null,
            publishedOn: $publishedDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $publishedDate) === 1
                ? $publishedDate
                : null,
            publishedYear: $publishedDate !== null && preg_match('/(\d{4})/', $publishedDate, $matches) === 1
                ? (int)$matches[1]
                : null,
            pages: is_int($volume['pageCount'] ?? null) && $volume['pageCount'] > 0 ? $volume['pageCount'] : null,
            language: BookLanguage::fromIso6391(is_string($volume['language'] ?? null) ? $volume['language'] : null),
            subjects: $this->subjects($volume),
            synopsis: is_string($volume['description'] ?? null) ? $volume['description'] : null,
            coverSourceUrl: $this->coverUrl($volume),
            source: 'google_books',
            raw: $volume,
        );
    }

    /**
     * @param  array<string, mixed>  $volume
     */
    private function industryIdentifier(array $volume, string $type): ?string
    {
        foreach (Arr::wrap($volume['industryIdentifiers'] ?? []) as $identifier) {
            if (is_array($identifier) && ($identifier['type'] ?? null) === $type) {
                return is_string($identifier['identifier'] ?? null) ? $identifier['identifier'] : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $volume
     * @return array<int, array{name: string, role: string}>
     */
    private function contributors(array $volume): array
    {
        $names = array_filter(Arr::wrap($volume['authors'] ?? []), is_string(...));

        return array_values(array_map(
            fn(string $name): array => ['name' => $name, 'role' => 'autor'],
            $names,
        ));
    }

    /**
     * Google's categories are free text, not a coded scheme like Thema or IBIC.
     *
     * @param  array<string, mixed>  $volume
     * @return array<int, array{scheme: string, code: string|null, heading: string|null}>
     */
    private function subjects(array $volume): array
    {
        $categories = array_filter(Arr::wrap($volume['categories'] ?? []), is_string(...));

        return array_values(array_map(
            fn(string $category): array => ['scheme' => 'text', 'code' => null, 'heading' => $category],
            $categories,
        ));
    }

    /**
     * @param  array<string, mixed>  $volume
     */
    private function coverUrl(array $volume): ?string
    {
        foreach (['extraLarge', 'large', 'medium', 'thumbnail', 'smallThumbnail'] as $size) {
            $url = data_get($volume, "imageLinks.{$size}");

            if (is_string($url) && filled($url)) {
                return str_replace('http://', 'https://', $url);
            }
        }

        return null;
    }

    private function request(): PendingRequest
    {
        return Http::withUserAgent(config('books.metadata.user_agent'))
            ->timeout(config('books.metadata.timeout'))
            ->retry(2, 200, throw: false);
    }
}
