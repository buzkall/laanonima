<?php

namespace App\Actions\Books;

use App\Models\Book;
use App\Support\BookMetadata\BookMetadata;
use App\Support\Isbn;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The slow half of cataloging a book off the shop's pool.
 *
 * `ImportShopBook` writes the row with no network call, because a reader is
 * waiting on it. This is everything the free ISBN sources can add on top --
 * binding, measurements, language, the edition, and a cover -- and it is three
 * providers with a five-second timeout apiece plus an image download, so it is
 * deferred past the response. Nothing on the page waits for it: the result
 * panel is drawn once and never re-rendered, and the book page it links to
 * reads perfectly well with a title over a flat color until the cover lands.
 *
 * Safe to run twice, and safe to fail. A provider having a bad day must leave a
 * working book page behind, never an exception in a terminating callback.
 */
class EnrichImportedBook
{
    /**
     * Columns the pool already answered better than any provider can, and that
     * a lookup must never rewrite.
     *
     * The title and the publisher are the shop's own, and `metadata_source` is
     * how a bookseller finds what La Cupida filed -- overwriting it with
     * "open_library" would lose the whole set.
     */
    private const array KEEP = ['isbn13', 'title', 'metadata_source'];

    public function __construct(
        private FetchBookMetadata $fetchMetadata,
        private AttachBookCover $attachCover,
    ) {}

    public function __invoke(Book $book): void
    {
        try {
            $this->ownTimeBudget();
            $this->enrich($book);
        } catch (Throwable $exception) {
            Log::warning('Could not enrich a book filed from the shop catalog.', [
                'isbn13'    => $book->isbn13,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Start the clock again before reaching for the network.
     *
     * `defer()` runs this inside the reader's own PHP process, after the
     * response is flushed but still under the `max_execution_time` that request
     * has been spending since it began -- and it began by waiting on a model.
     * Three providers at five seconds apiece plus a cover download does not fit
     * in what is left of thirty seconds, and the process is killed mid-lookup:
     * the book is filed and correct, but nothing was enriched and the fatal
     * lands in the log with no warning of ours beside it, which is exactly how
     * this was found.
     *
     * `set_time_limit()` restarts the counter rather than adding to it. It is a
     * no-op where the SAPI forbids it, which is why the outcome is not checked.
     */
    private function ownTimeBudget(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit((int)config('books.metadata.enrich_time_limit'));
        }
    }

    private function enrich(Book $book): void
    {
        $metadata = Isbn::isValid($book->isbn13)
            ? ($this->fetchMetadata)($book->isbn13)
            : null;

        if ($metadata instanceof BookMetadata) {
            $book->fill($this->gaps($book, $metadata))->save();
        }

        /* Roughly one Spanish ISBN in six has no cover at any free source, and
           the shop resizes on demand for every EAN it stocks -- so its own
           image is the last one worth trying before a book goes on the shelf
           with no picture at all. Asked for at the width covers are stored at,
           not the 400 the result panel hotlinks. */
        if (blank($book->cover_source_url)) {
            $book->fill(['cover_source_url' => $this->shopCoverUrl($book)])->save();
        }

        /* After the save, and only ever off `cover_source_url`: the download is
           guarded by the host allowlist in config/books.php, and the color is
           read from whatever lands by the media listener in AppServiceProvider,
           so nothing here computes a palette. */
        ($this->attachCover)($book);

        /* Stamped whatever happened, because it records that we looked and not
           that we found. Most of these books are recent Spanish titles no free
           source knows, and a column only written on a hit would leave
           `books:enrich` picking the same ones up on every run forever. */
        $book->forceFill(['metadata_synced_at' => now()])->saveQuietly();
    }

    /**
     * What the lookup may fill in: the empty columns, and nothing else.
     *
     * Gap-fill rather than overwrite, the way `books:measure` already works. A
     * bookseller who has corrected a page count keeps their number, and so does
     * the shop's own price.
     *
     * The synopsis is the one exception. The scrape truncates it to
     * `cupida.synopsis_limit` characters, so the pool's version routinely stops
     * mid-sentence; a provider's full text is a better paragraph to put on a
     * public page than an ellipsis.
     *
     * @return array<string, mixed>
     */
    private function gaps(Book $book, BookMetadata $metadata): array
    {
        $attributes = array_diff_key($metadata->toBookAttributes(), array_flip(self::KEEP));

        $gaps = array_filter(
            $attributes,
            fn(string $field): bool => blank($book->getAttribute($field)),
            ARRAY_FILTER_USE_KEY,
        );

        if ($this->synopsisIsTruncated($book) && filled($metadata->synopsis)) {
            $gaps['synopsis'] = $metadata->synopsis;
        }

        return $gaps;
    }

    private function shopCoverUrl(Book $book): string
    {
        $base = rtrim((string)config('cupida.scrape.base_url'), '/');
        $width = (int)config('books.covers.max_width');

        return "{$base}/imagen.php?ean={$book->isbn13}&ancho={$width}";
    }

    /**
     * The scrape cuts a synopsis at the limit and marks it, so this is the
     * shop's own sentence ending rather than a guess at one.
     */
    private function synopsisIsTruncated(Book $book): bool
    {
        return filled($book->synopsis) && str_ends_with(rtrim((string)$book->synopsis), '...');
    }
}
