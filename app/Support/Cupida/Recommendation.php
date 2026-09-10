<?php

namespace App\Support\Cupida;

use App\Models\Book;
use App\Support\CoverPalette;

/**
 * The one book, ready to be drawn.
 *
 * A recommendation comes from two places at once. The book itself is always out
 * of the scraped pool -- that is what guarantees it is in stock -- but the
 * reader is sent to *our* page for it, with our cover and our color, whenever
 * the EAN is also a row in `books`. It usually is not, so `RecommendBook` files
 * one before this is drawn (`ImportShopBook`) and swaps the result through
 * `withLocalBook()`. The shop's own page is kept alongside in `shopUrl` and
 * offered underneath, because a reader who wants to see it on the site they
 * know should not have to search for it.
 *
 * `url` therefore points at the old site only when the book could not be filed
 * -- it is already ours and un-published, the import is switched off, or it
 * failed -- which is the one case where the shop's page is the better answer
 * anyway.
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
        public ?string $synopsis,
        public string $url,
        public string $shopUrl,
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
        $shopUrl = self::shopUrl($book);

        return new self(
            ean: $ean,
            title: (string)$book['title'],
            author: is_string($book['author'] ?? null) ? CupidaCatalog::readableName($book['author']) : null,
            publisher: is_string($book['publisher'] ?? null) ? $book['publisher'] : null,
            pitch: $pitch,
            matchLine: $matchLine,
            synopsis: self::synopsis($local, $book),
            url: $local instanceof Book ? route('books.show', $local) : $shopUrl,
            shopUrl: $shopUrl,
            coverUrl: $local?->coverUrl('thumb') ?? self::coverUrl($ean),
            palette: CoverPalette::fromCover($local?->cover_color),
            book: $local,
            written: $written,
            cost: $cost,
        );
    }

    /**
     * The same recommendation, now that the book is one of ours.
     *
     * `RecommendBook` files the book after this object has been built, so this
     * is what moves the link over without asking the pool for the row again.
     * The cover and the color follow the same rules `make()` uses: a row filed
     * a moment ago has no image yet, so both fall back to the shop's and the
     * panel is drawn exactly as it would have been -- which is the point. The
     * result screen renders once and is never re-rendered, so a cover arriving
     * later cannot repaint the page under a reader mid-paragraph; it is simply
     * there on the next session.
     */
    public function withLocalBook(Book $book): self
    {
        return new self(
            ean: $this->ean,
            title: $this->title,
            author: $this->author,
            publisher: $this->publisher,
            pitch: $this->pitch,
            matchLine: $this->matchLine,
            synopsis: self::text($book->synopsis) ?? $this->synopsis,
            url: route('books.show', $book),
            shopUrl: $this->shopUrl,
            coverUrl: $book->coverUrl('thumb') ?? $this->coverUrl,
            palette: CoverPalette::fromCover($book->cover_color),
            book: $book,
            written: $this->written,
            cost: $this->cost,
        );
    }

    /**
     * What the book is about, in whichever catalog says it best.
     *
     * Ours first and the pool second, the same order `url` and `coverUrl` use,
     * and for a plainer reason than either: `cupida:scrape` stores the shop's
     * text through `Str::limit()` at `cupida.synopsis_limit`, so every one of
     * them stops at six hundred characters and most stop mid-word. A book that
     * is also a `books` row has the whole thing -- which is the only difference
     * between the two. Everything else wrong with the text is wrong in both.
     *
     * It is the shop's description and not the librera's, which is the point of
     * showing it: the pitch is somebody telling you to read this, and a reader
     * deciding wants the other thing as well. Null rather than an empty string
     * when neither catalog has one -- the panel renders nothing at all then,
     * and a heading over a blank is worse than no heading.
     *
     * @param  array<string, mixed>  $book  a row out of the scraped pool
     */
    private static function synopsis(?Book $local, array $book): ?string
    {
        $text = self::text($local?->synopsis) ?? self::text($book['synopsis'] ?? null);

        /* Both are repaired, not only the pool's. `ImportShopBook` files our row
           from the same listing, so a `books` row carries the shop's run-together
           sentences exactly as the pool does -- the defect follows the text, not
           where it was read from. Only the truncation is one-sided, and
           `lastWholeSentence()` says so itself by looking for the ellipsis
           rather than for which catalog answered. */
        return $text === null ? null : self::lastWholeSentence(self::spaced($text));
    }

    /**
     * The shop's blurb, with the space back after the full stop.
     *
     * Their listing runs sentences together -- "una Isabel Allende mas Allende
     * que nunca.Un regalo para todos sus lectores" -- often where a cover quote
     * has been pasted onto the description. `ShopScraper` is not doing it: the
     * page arrives that way, and a re-scrape brings it back, which is why the
     * repair is here rather than in `books.json`.
     *
     * A lower-case letter, a full stop, an upper-case letter: narrow enough
     * that "EE.UU." keeps its shape and an initial in a name is not touched.
     * It was not worth doing while this text only fed the scoring, which does
     * not read; it is the first thing a reader sees now that the panel does.
     */
    private static function spaced(string $text): string
    {
        return (string)preg_replace('/(\p{Ll})\.(\p{Lu})/u', '$1. $2', $text);
    }

    /**
     * The pool's text back to where it stops making sense.
     *
     * `Str::limit()` counts characters and nothing else, so the six hundredth
     * one lands wherever it lands: "sigue a un puñado de extraordin...". That
     * was invisible while the synopsis only fed the scoring, and is the first
     * thing a reader sees now that the panel shows it, so the tail is dropped
     * back to the last sentence that finished.
     *
     * Only for text that was actually cut -- `Str::limit()` leaves its "..."
     * behind, and a synopsis short enough to arrive whole must not lose its
     * last sentence to a rule meant for the truncated ones. A blurb with no
     * sentence break at all is left exactly as it is: half of something is
     * better than none of it.
     */
    private static function lastWholeSentence(string $text): string
    {
        if (! str_ends_with($text, '...') && ! str_ends_with($text, '…')) {
            return $text;
        }

        $cut = rtrim($text, '.… ');
        $end = mb_strrpos($cut, '. ');

        return $end === false ? $text : mb_substr($cut, 0, $end + 1);
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Is this book one of ours as well as one of theirs?
     *
     * Matched on both columns because the two are not the same thing for every
     * record: a book catalogd here from an ISBN has `isbn13`, one catalogd
     * from a barcode has `ean13`, and the shop's number can be either.
     *
     * Scoped to what is on the web, unlike `ImportShopBook::existing()`: a book
     * a bookseller has un-published is a 404, so the reader belongs on the
     * shop's page for it.
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
