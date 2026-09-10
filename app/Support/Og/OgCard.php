<?php

namespace App\Support\Og;

use App\Actions\Images\MediaBytes;
use App\Models\Author;
use App\Models\Book;
use App\Models\CupidaRecommendation;
use App\Models\Publisher;
use App\Support\CoverPalette;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The finished description of one share card: everything the compositor draws,
 * and nothing that knows what a book is.
 *
 * The three named constructors are the only place the domain appears, which is
 * what lets a book, a shelf of an author's work and an imprint share one
 * drawing routine instead of three that drift apart. Each one reads the bytes
 * it needs, so building an OgCard is the expensive half -- OgCardKey is the
 * cheap half a page holds on every render.
 */
final readonly class OgCard
{
    /**
     * @param  list<string>  $images  raw bytes, front-most first
     */
    private function __construct(
        public string $title,
        public string $subtitle,
        public CoverPalette $palette,
        public array $images,
    ) {}

    public static function forBook(Book $book, MediaBytes $bytes = new MediaBytes): self
    {
        $cover = $bytes($book->cover(), 'retina');

        return new self(
            title: $book->title,
            subtitle: filled($book->authors_line)
                ? (string)__('books.public.share.by', ['authors' => $book->authors_line])
                : '',
            palette: CoverPalette::fromCover($book->cover_color),
            images: $cover === null ? [] : [$cover],
        );
    }

    public static function forAuthor(Author $author, int $bookCount, MediaBytes $bytes = new MediaBytes): self
    {
        return new self(
            title: $author->name,
            subtitle: self::shelfCount($bookCount),
            /* The house color, the same one BookController::author() hands the
               page, so the card and the page it advertises cannot disagree. */
            palette: CoverPalette::fromCover(null),
            /* The same query the page runs, and deliberately not
               `$author->books()`: that relation is ->distinct(), and Postgres
               refuses a SELECT DISTINCT whose ORDER BY names expressions that
               are not in the select list -- which is exactly what onShelf()
               adds. SQLite runs it happily, so the tests never see it. */
            images: self::coversOf(
                Book::query()->onShelf()
                    ->whereHas('contributors', fn(Builder $query): Builder => $query->whereBelongsTo($author))
                    ->take((int)config('og.images.max'))
                    ->get(),
                $bytes,
            ),
        );
    }

    public static function forPublisher(Publisher $publisher, int $bookCount, MediaBytes $bytes = new MediaBytes): self
    {
        $logo = $bytes($publisher->getFirstMedia(Publisher::LOGO_COLLECTION));

        return new self(
            title: $publisher->name,
            subtitle: self::shelfCount($bookCount),
            palette: CoverPalette::fromCover(null),
            /* An imprint speaks with its logo when it has one; without, it is
               known by what it publishes. */
            images: $logo !== null
                ? [$logo]
                : self::coversOf(
                    Book::query()->onShelf()->whereBelongsTo($publisher)->take((int)config('og.images.max'))->get(),
                    $bytes,
                ),
        );
    }

    /**
     * The card a shared recommendation is previewed with.
     *
     * The line goes in `title` and not in `subtitle`, which is the opposite of
     * the other three and is the only reason this fits the same drawing: title
     * is the slot that autoshrinks and wraps over `og.title.lines`, and a match
     * line runs to a sentence where "de Paco Roca" runs to three words.
     *
     * Which is also why the subtitle names the writer and not the book: it is a
     * fixed 22pt with no shrink and fits about thirty characters, so a title
     * put there is a title that pushes the author off the end -- measured, on
     * "La puerta del viaje sin retorno", which drew as "LA PUERTA DEL VIAJE SIN
     * RETORN...". The book is already the largest thing on the card, printed on
     * its own cover.
     *
     * The cover is read from our own media and never from the shop: a book we
     * have not catalogd has its cover behind an HTTP request, and this runs
     * inside the first page view. Handing over no image at all is the right
     * answer there -- the compositor already draws its own plate with the
     * isotipo on it, in this card's palette.
     */
    public static function forRecommendation(CupidaRecommendation $recommendation, MediaBytes $bytes = new MediaBytes): self
    {
        $book = $recommendation->book;
        $cover = $book instanceof Book ? $bytes($book->cover(), 'retina') : null;

        return new self(
            title: self::line($recommendation),
            subtitle: $recommendation->author === null
                ? Str::limit($recommendation->title, 38)
                : (string)__('cupida.result.by', ['author' => $recommendation->author]),
            palette: CoverPalette::fromCover($book?->cover_color),
            images: $cover === null ? [] : [$cover],
        );
    }

    /**
     * What the card says: the match line when there is one, and the pitch's
     * first sentence when there is not.
     *
     * The fallback pitch is a paragraph and the whole of it on a card is a wall
     * -- one sentence of it still sounds like her, which is what the card is
     * for.
     */
    private static function line(CupidaRecommendation $recommendation): string
    {
        $line = trim((string)$recommendation->match_line);

        if ($line !== '') {
            return $line;
        }

        $first = preg_split('/(?<=[.!?])\s+/u', trim($recommendation->pitch), 2);

        return Str::limit($first[0] ?? $recommendation->title, 120);
    }

    /**
     * @param  Collection<int, Book>  $books
     * @return list<string>
     */
    private static function coversOf(Collection $books, MediaBytes $bytes): array
    {
        $covers = [];

        foreach ($books as $book) {
            $cover = $bytes($book->cover(), 'retina');

            if ($cover !== null) {
                $covers[] = $cover;
            }

            if (count($covers) === (int)config('og.images.max')) {
                break;
            }
        }

        return $covers;
    }

    private static function shelfCount(int $books): string
    {
        return trans_choice('books.public.share.count', $books, ['count' => $books]);
    }
}
