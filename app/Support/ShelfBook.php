<?php

namespace App\Support;

use App\Enums\BookBinding;
use App\Models\Book;

/**
 * One book as a physical object, ready to be stood on the shelf at /estanteria.
 *
 * That page is the catalogue drawn to scale: every book on it is as tall, as
 * wide and as thick on screen as it is in the shop, which is the whole point of
 * it and the one thing the grid on the home page cannot show. So this is where
 * the three measurements are settled -- read off the record when the ISBN
 * lookup found them, and estimated from the binding and the page count when it
 * did not, because otherwise nine books in ten would have no size at all and
 * the shelf would be a single title wide.
 *
 * Estimated is never presented as measured: `isMeasured` says which it was, and
 * the page tells the reader.
 */
final readonly class ShelfBook
{
    private function __construct(
        public Book $book,
        /** Millimetres, as they would be with a ruler against the book. */
        public int $widthMm,
        public int $heightMm,
        public int $thicknessMm,
        /** True only when both long sides came off the record. */
        public bool $isMeasured,
        /** Whether this one is turned cover-first rather than spine-first. */
        public bool $facesOut,
        /** The pile it lies in, if it is lying flat rather than standing. */
        public ?int $stack,
        /** The cover colour, and what can be read over it: the spine's paint. */
        public CoverPalette $palette,
    ) {}

    public static function from(Book $book): self
    {
        [$width, $height, $measured] = self::sides($book);

        return new self(
            book: $book,
            widthMm: $width,
            heightMm: $height,
            thicknessMm: self::thickness($book),
            isMeasured: $measured,
            facesOut: false,
            stack: null,
            palette: CoverPalette::fromCover($book->cover_color),
        );
    }

    /**
     * The same book, once the shelf has decided where it goes.
     *
     * Measuring has to come first -- a pile is ordered by how much of the board
     * each book covers -- so the size is settled in `from()` and the placement
     * is added here, rather than both being guessed at once.
     */
    public function placed(bool $facesOut, ?int $stack): self
    {
        return new self(
            book: $this->book,
            widthMm: $this->widthMm,
            heightMm: $this->heightMm,
            thicknessMm: $this->thicknessMm,
            isMeasured: $this->isMeasured,
            facesOut: $facesOut,
            stack: $stack,
            palette: $this->palette,
        );
    }

    public function liesFlat(): bool
    {
        return $this->stack !== null;
    }

    /**
     * The area this book covers when it is lying on its back, which is what
     * decides where it goes in a pile: the biggest one holds up the rest.
     */
    public function footprintArea(): int
    {
        return $this->widthMm * $this->heightMm;
    }

    /**
     * What is written down the spine. A surname is enough to find a book by --
     * a full name in six-point type down 15mm of card is not.
     */
    public function spineLine(): string
    {
        $author = $this->book->contributorNames('author')[0] ?? null;
        $surname = $author === null ? null : last(explode(' ', $author));

        return trim($this->book->title . ($surname === null ? '' : ' · ' . $surname));
    }

    /**
     * The two long sides, and whether they were measured or guessed.
     *
     * Both or neither: half a record -- a height with no width -- would stand a
     * book at the wrong proportions, which reads as a mistake rather than as an
     * estimate.
     *
     * @return array{int, int, bool}
     */
    private static function sides(Book $book): array
    {
        if ($book->width_mm !== null && $book->height_mm !== null) {
            return [$book->width_mm, $book->height_mm, true];
        }

        $sizes = config('site.shelf.sizes');
        [$width, $height] = $sizes[$book->binding->value ?? ''] ?? $sizes['default'];

        return [(int)$width, (int)$height, false];
    }

    /**
     * How thick the spine is: measured, or paper plus covers.
     */
    private static function thickness(Book $book): int
    {
        if ($book->thickness_mm !== null) {
            return $book->thickness_mm;
        }

        if ($book->pages === null) {
            return (int)config('site.shelf.default_thickness_mm');
        }

        $boards = $book->binding === BookBinding::Hardback
            ? (int)config('site.shelf.mm_for_boards')
            : (int)config('site.shelf.mm_for_covers');

        return (int)max(
            config('site.shelf.min_thickness_mm'),
            round($book->pages * (float)config('site.shelf.mm_per_page')) + $boards,
        );
    }
}
