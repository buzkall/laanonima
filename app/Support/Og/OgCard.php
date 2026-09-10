<?php

namespace App\Support\Og;

use App\Actions\Images\MediaBytes;
use App\Models\Author;
use App\Models\Book;
use App\Models\Publisher;
use App\Support\CoverPalette;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
