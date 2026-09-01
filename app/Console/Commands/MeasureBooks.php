<?php

namespace App\Console\Commands;

use App\Actions\Books\FetchBookMetadata;
use App\Models\Book;
use App\Support\BookMetadata\BookMetadata;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Run the ISBN lookup over the catalogue and write down how big the books are.
 *
 * The shelf at /estanteria is drawn to scale, so a record with no measurements
 * is stood up at the ordinary size for its binding instead. This is how those
 * estimates are replaced with the real thing, one shelf-wide pass at a time.
 *
 * It only ever fills gaps. A measurement already on the record was either
 * typed by a bookseller with the book in hand or found on an earlier run, and
 * both beat whatever a free catalogue says today.
 */
#[Signature('books:measure {--fresh : Ignore the cached lookups and ask the sources again} {--all : Include books that are already measured}')]
#[Description('Fill in the physical measurements of the catalogue from the ISBN sources')]
class MeasureBooks extends Command
{
    /** The columns this command is allowed to touch. */
    private const array MEASUREMENTS = ['height_mm', 'width_mm', 'thickness_mm', 'weight_grams'];

    /**
     * What to print once the pass is over.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private array $rows = [];

    public function handle(FetchBookMetadata $fetchMetadata): int
    {
        $books = $this->books();

        if ($books->isEmpty()) {
            $this->components->info('Every book is already measured.');

            return self::SUCCESS;
        }

        $measured = 0;
        $missed = 0;

        $this->withProgressBar($books, function(Book $book) use ($fetchMetadata, &$measured, &$missed): void {
            if ($this->option('fresh')) {
                Cache::forget("book-metadata:v2:{$book->isbn13}");
            }

            $filled = $this->measure($book, $fetchMetadata($book->isbn13));

            $filled === [] ? $missed++ : $measured++;

            $this->rows[] = [
                $book->isbn13,
                str($book->title)->limit(38)->value(),
                $filled === [] ? '—' : implode(', ', $filled),
            ];
        });

        $this->newLine(2);
        $this->table(['ISBN', 'Title', 'Filled in'], $this->rows);

        $this->components->info("{$measured} measured, {$missed} the sources could not measure.");

        /*
         | Say why plainly. Open Library carries physical_dimensions for only a
         | fraction of Spanish editions, and Google Books -- which does file
         | them, per side -- is inert until GOOGLE_BOOKS_API_KEY is set, so a
         | run that finds nothing is the ordinary outcome rather than a fault.
         */
        if ($missed > 0 && blank(config('books.metadata.google_books.key'))) {
            $this->components->warn(
                'GOOGLE_BOOKS_API_KEY is not set, so only Open Library was asked. '
                . 'It files measurements for a minority of Spanish editions; Google Books files them per side.',
            );
        }

        return self::SUCCESS;
    }

    /**
     * What this run has to ask about: by default only the books that are still
     * standing on the shelf at an estimated size.
     *
     * @return Collection<int, Book>
     */
    private function books()
    {
        return Book::query()
            ->unless($this->option('all'), function($query): void {
                $query->where(function($query): void {
                    foreach (self::MEASUREMENTS as $column) {
                        $query->orWhereNull($column);
                    }
                });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Write down whatever came back that the record does not already have.
     *
     * @return array<int, string> the columns that were filled in
     */
    private function measure(Book $book, ?BookMetadata $metadata): array
    {
        if (! $metadata instanceof BookMetadata) {
            return [];
        }

        $found = array_intersect_key($metadata->toBookAttributes(), array_flip(self::MEASUREMENTS));
        $filled = [];

        foreach ($found as $column => $value) {
            if ($book->{$column} === null) {
                $book->{$column} = $value;
                $filled[] = $column;
            }
        }

        if ($filled !== []) {
            $book->save();
        }

        return $filled;
    }
}
