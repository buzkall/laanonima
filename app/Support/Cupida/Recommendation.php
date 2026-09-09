<?php

namespace App\Support\Cupida;

use App\Models\Book;
use App\Support\CoverPalette;

/**
 * The one book, ready to be drawn.
 *
 * A recommendation can come from two places at once. The book itself is always
 * out of the scraped pool -- that is what guarantees it is in stock -- but the
 * shop's catalog and this site's own catalog overlap, so when the EAN is
 * also a row in `books` the reader is sent to our page for it, with our cover
 * and our color, instead of out to the old site.
 *
 * `written` says whether the pitch came from the model or from the fallback
 * line. Nothing on the page reads differently either way; it is there so a test
 * can tell the two paths apart and so a log can say which one a reader got.
 * `cost` sits in the same slot: it is what the pitch cost the shop, on its way
 * to the row the bookseller reads, and the panel is the only thing that ever
 * looks at it. Both are null on the object the component rebuilds to draw the
 * result, which keeps a price out of a payload the browser round-trips.
 */
final readonly class Recommendation
{
    public function __construct(
        public string $ean,
        public string $title,
        public ?string $author,
        public ?string $publisher,
        public string $pitch,
        public ?string $matchLine,
        public string $url,
        public ?string $coverUrl,
        public CoverPalette $palette,
        public ?Book $book,
        public bool $written,
        public ?PromptCost $cost = null,
    ) {}

    /**
     * @param  array<string, mixed>  $book  a row out of the scraped pool
     */
    public static function make(array $book, string $pitch, ?string $matchLine, bool $written, ?PromptCost $cost = null): self
    {
        $ean = (string)$book['ean'];
        $local = self::localBook($ean);

        return new self(
            ean: $ean,
            title: (string)$book['title'],
            author: is_string($book['author'] ?? null) ? CupidaCatalog::readableName($book['author']) : null,
            publisher: is_string($book['publisher'] ?? null) ? $book['publisher'] : null,
            pitch: $pitch,
            matchLine: $matchLine,
            url: $local instanceof Book
                ? route('books.show', $local)
                : self::shopUrl($book),
            coverUrl: $local?->coverUrl('thumb') ?? self::coverUrl($ean),
            palette: CoverPalette::fromCover($local?->cover_color),
            book: $local,
            written: $written,
            cost: $cost,
        );
    }

    /**
     * Is this book one of ours as well as one of theirs?
     *
     * Matched on both columns because the two are not the same thing for every
     * record: a book catalogd here from an ISBN has `isbn13`, one catalogd
     * from a barcode has `ean13`, and the shop's number can be either.
     */
    private static function localBook(string $ean): ?Book
    {
        return Book::query()
            ->active()
            ->where(fn($query) => $query->where('isbn13', $ean)->orWhere('ean13', $ean))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $book
     */
    private static function shopUrl(array $book): string
    {
        $base = rtrim((string)config('cupida.scrape.base_url'), '/');

        return "{$base}/libros/{$book['ean']}/{$book['slug']}/";
    }

    /**
     * The shop resizes covers on demand, so this URL exists for any EAN it
     * stocks. It is the one image the page borrows from the old site, and only
     * when we have no cover of our own.
     */
    private static function coverUrl(string $ean): string
    {
        $base = rtrim((string)config('cupida.scrape.base_url'), '/');

        return "{$base}/imagen.php?ean={$ean}&ancho=400";
    }
}
