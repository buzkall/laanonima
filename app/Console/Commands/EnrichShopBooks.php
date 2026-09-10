<?php

namespace App\Console\Commands;

use App\Actions\Books\EnrichImportedBook;
use App\Actions\Books\ImportShopBook;
use App\Models\Book;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finish cataloging the books La Cupida filed.
 *
 * The recommendation writes the row out of the scraped pool and defers the rest
 * -- the ISBN lookup and the cover -- to run after the response. That is the
 * right place for it, and it is also the one that can quietly not happen: the
 * reader closes the tab, the provider hangs, the process runs out of its second
 * of time. Nothing is lost when it does, because the row is already correct and
 * on the web; what is missing is a cover and a few measurements.
 *
 * So this is the sweep that catches up, and it is safe to run as often as you
 * like: `EnrichImportedBook` fills blanks and never writes over anything, and a
 * book it has already been through has a `metadata_synced_at`.
 */
#[Description('Fill in the covers and measurements for books La Cupida catalogd')]
#[Signature('books:enrich
        {--limit=50 : How many books to work through in one run}
        {--all : Include books that have been through a lookup before}')]
class EnrichShopBooks extends Command
{
    public function handle(EnrichImportedBook $enrich): int
    {
        $books = Book::query()
            ->where('metadata_source', ImportShopBook::SOURCE)
            ->when(! $this->option('all'), fn(Builder $query): Builder => $query->whereNull('metadata_synced_at'))
            ->oldest('id')
            ->limit((int)$this->option('limit'))
            ->get();

        if ($books->isEmpty()) {
            $this->info('Nothing left to enrich.');

            return self::SUCCESS;
        }

        foreach ($books as $book) {
            $enrich($book);

            $this->line(sprintf(
                '%s  %s  %s',
                $book->isbn13,
                $book->fresh()->hasMedia(Book::COVERS_COLLECTION) ? 'cover' : '  -  ',
                $book->title,
            ));
        }

        $left = Book::query()
            ->where('metadata_source', ImportShopBook::SOURCE)
            ->whereNull('metadata_synced_at')
            ->count();

        $this->info("{$books->count()} enriched, {$left} still waiting.");

        return self::SUCCESS;
    }
}
