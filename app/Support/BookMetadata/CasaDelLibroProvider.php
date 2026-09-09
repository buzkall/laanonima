<?php

namespace App\Support\BookMetadata;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Casa del Libro's cover CDN -- a cover, and deliberately nothing else.
 *
 * It is last in the chain and answers with one field, because that is the one
 * thing the free sources are worst at for Spanish books: Open Library's cover
 * archive is thin outside English, and Google Books is inert without a key.
 * The shop's Spanish stock is exactly what Casa del Libro carries, so a
 * bookseller who has just typed an Anagrama ISBN gets a picture instead of an
 * empty cover field.
 *
 * The URL is derived, not searched: the file is filed under the ISBN's last
 * two digits and named after the ISBN, so no page has to be scraped and there
 * is nothing to keep working when their markup changes. A miss is a plain 404
 * -- unlike the sources that answer one with a placeholder and a 200 -- which
 * is why a HEAD is enough to tell a real cover from a missing one.
 */
class CasaDelLibroProvider implements BookMetadataProvider
{
    /**
     * The four `imagessl1..4` hosts serve the same files; the seeded covers
     * already in `database/seeders/data/books.json` all name the third.
     */
    private const string COVERS_ENDPOINT = 'https://imagessl3.casadellibro.com/a/l/t0';

    public function find(string $isbn13): ?BookMetadata
    {
        $url = self::COVERS_ENDPOINT . '/' . substr($isbn13, -2) . "/{$isbn13}.jpg";

        try {
            if (! $this->request()->head($url)->successful()) {
                return null;
            }
        } catch (Throwable $exception) {
            Log::debug('Casa del Libro cover probe failed.', ['isbn13' => $isbn13, 'exception' => $exception->getMessage()]);

            return null;
        }

        return new BookMetadata(
            isbn13: $isbn13,
            coverSourceUrl: $url,
            source: 'casa_del_libro',
        );
    }

    /**
     * Retried only when the connection itself failed.
     *
     * The other providers retry on any unsuccessful response, which is right
     * for an API that answers a hiccup with a 500. Here the only status that
     * matters is the 404 that says "no cover for this ISBN", and asking a
     * second time cannot change it -- so a plain retry would double every miss.
     */
    private function request(): PendingRequest
    {
        return Http::withUserAgent(config('books.metadata.user_agent'))
            ->timeout(config('books.metadata.timeout'))
            ->retry(2, 200, when: fn(Throwable $exception): bool => $exception instanceof ConnectionException, throw: false);
    }
}
