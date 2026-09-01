<?php

namespace App\Support;

use App\Models\Book;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * How the books are laid along the shelf at /estanteria.
 *
 * A physical shelf has no first place to give a book, so the row is shuffled
 * rather than sorted. A couple are turned cover-first the way a bookseller
 * turns a few towards the door, and a few more lie flat in a pile, which is
 * what a shelf looks like when it is used rather than dressed.
 *
 * Every one of those decisions is taken here, from one seed, so a shelf a
 * reader saw can be put back exactly as it was: the seed is written onto the
 * page as `data-seed`.
 *
 * The row is built as *slots* rather than as books, because a pile is one place
 * on the board holding two or three books. Which books are on the shelf at all
 * is decided before this, by `Book::onShelf()`; this arranges what it is handed
 * and never chooses the stock.
 */
final readonly class ShelfArrangement
{
    /**
     * @param  Collection<int, ShelfBook>  $books  in row order, bottom of a pile first
     */
    private function __construct(
        /** Reproduces this exact shelf. Written to the page as data-seed. */
        public int $seed,
        public Collection $books,
    ) {}

    /**
     * @param  EloquentCollection<int, Book>  $books
     */
    public static function of(EloquentCollection $books, ?int $seed = null): self
    {
        $seed ??= random_int(0, PHP_INT_MAX);
        $randomizer = new Randomizer(new Mt19937($seed));

        /** @var array<int, ShelfBook> $shuffled */
        $shuffled = $randomizer->shuffleArray($books->map(ShelfBook::from(...))->all());

        $slots = self::intoSlots($randomizer, $shuffled);
        $facingOut = self::pickFacingOut($randomizer, $slots);

        $placed = [];

        foreach ($slots as $index => $slot) {
            $isPile = count($slot) > 1;

            foreach ($slot as $shelfBook) {
                $placed[] = $shelfBook->placed(
                    facesOut: ! $isPile && in_array($index, $facingOut, true),
                    stack: $isPile ? $index : null,
                );
            }
        }

        return new self(seed: $seed, books: new Collection($placed));
    }

    /**
     * The places along the board, each holding one standing book or one pile.
     *
     * At most `site.shelf.stacks` piles, and only on a shelf with enough books
     * to spare: three of five lying down reads as a mess rather than a shelf.
     * Within a pile the biggest book goes on the bottom, because that is both
     * what holds the rest up and what anyone stacking books actually does.
     *
     * @param  array<int, ShelfBook>  $books
     * @return array<int, array<int, ShelfBook>>
     */
    private static function intoSlots(Randomizer $randomizer, array $books): array
    {
        $slots = array_map(fn(ShelfBook $book): array => [$book], $books);
        $count = count($books);

        if ($count < (int)config('site.shelf.stack_needs')) {
            return $slots;
        }

        [$least, $most] = config('site.shelf.stack_size');

        for ($pile = 0; $pile < (int)config('site.shelf.stacks'); $pile++) {
            $size = $randomizer->getInt((int)$least, (int)$most);

            /* Only ever out of slots that still hold a single book, so a second
               pile cannot swallow the first. */
            $start = self::runOfSingles($randomizer, $slots, $size);

            if ($start === null) {
                break;
            }

            $taken = array_merge(...array_slice($slots, $start, $size));
            usort($taken, fn(ShelfBook $a, ShelfBook $b): int => $b->footprintArea() <=> $a->footprintArea());

            array_splice($slots, $start, $size, [$taken]);
        }

        return $slots;
    }

    /**
     * Where a pile of this many can be taken out of the row, or null if there
     * is no run of single books long enough left.
     *
     * @param  array<int, array<int, ShelfBook>>  $slots
     */
    private static function runOfSingles(Randomizer $randomizer, array $slots, int $size): ?int
    {
        $starts = [];
        $places = count($slots);

        for ($at = 0; $at + $size <= $places; $at++) {
            $run = array_slice($slots, $at, $size);

            $piles = array_filter($run, fn(array $slot): bool => count($slot) > 1);

            if ($piles === []) {
                $starts[] = $at;
            }
        }

        return $starts === [] ? null : $starts[$randomizer->getInt(0, count($starts) - 1)];
    }

    /**
     * Which places along the row are turned cover-first, never two side by side.
     *
     * Two covers next to each other read as a mistake at the table rather than
     * as a choice, so a place next to one already chosen is passed over. Piles
     * are not candidates: a book lying flat has no cover to turn outwards.
     *
     * @param  array<int, array<int, ShelfBook>>  $slots
     * @return array<int, int>
     */
    private static function pickFacingOut(Randomizer $randomizer, array $slots): array
    {
        $wanted = (int)config('site.shelf.facing_out');
        $candidates = [];

        foreach ($slots as $index => $slot) {
            if (count($slot) === 1) {
                $candidates[] = $index;
            }
        }

        $chosen = [];

        foreach ($randomizer->shuffleArray($candidates) as $place) {
            if (count($chosen) >= $wanted) {
                break;
            }

            $neighbours = array_filter($chosen, fn(int $taken): bool => abs($taken - $place) < 2);

            if ($neighbours === []) {
                $chosen[] = $place;
            }
        }

        return $chosen;
    }
}
