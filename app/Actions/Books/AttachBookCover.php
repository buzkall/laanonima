<?php

namespace App\Actions\Books;

use App\Enums\BookCoverOutcome;
use App\Models\Book;

/**
 * File the cover the ISBN lookup found, once the book exists to hang it on.
 *
 * The lookup runs inside the form, where on a create page there is no record
 * yet and therefore no media collection to add to. So it only records where
 * the cover can be fetched from, in cover_source_url, and the download happens
 * here after the save.
 *
 * A book that already has images keeps them: the lookup fills in a gap, it
 * never overrules what the bookseller uploaded.
 */
class AttachBookCover
{
    public function __construct(private DownloadBookCover $downloadCover) {}

    public function __invoke(Book $book): BookCoverOutcome
    {
        if (blank($book->cover_source_url) || $this->hasImages($book)) {
            return BookCoverOutcome::Skipped;
        }

        $jpeg = ($this->downloadCover)($book->cover_source_url, $book->isbn13);

        if ($jpeg === null) {
            return BookCoverOutcome::Failed;
        }

        $book->addCoverFromString($jpeg);

        return BookCoverOutcome::Attached;
    }

    /**
     * Asked of the database rather than through hasMedia(), because this runs
     * right after Filament saved the form's own uploads and the record's media
     * relation is still the empty collection it was loaded with.
     */
    private function hasImages(Book $book): bool
    {
        return $book->media()
            ->where('collection_name', Book::COVERS_COLLECTION)
            ->exists();
    }
}
