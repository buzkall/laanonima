<?php

namespace App\Actions\Books;

use App\Models\Book;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Keep books.cover_color in step with whichever image currently leads.
 *
 * The colour cannot be derived while the book is being saved: media library
 * attaches a cover *after* the model is written, so a saving hook only ever
 * sees the state before the cover arrived. The trigger is therefore the media
 * itself — added, deleted, or reordered — wired up in AppServiceProvider.
 *
 * Reordering matters as much as adding: the first image in the collection is
 * the cover, so dragging another one to the front changes the colour.
 */
class SyncCoverColor
{
    public function __construct(private ExtractCoverColor $extractColor) {}

    /**
     * @return string|null the colour now stored
     */
    public function __invoke(Book $book): ?string
    {
        /* The relation is stale by definition here: the media that triggered
           this was written after the book was loaded. */
        $book->load('media');

        $color = ($this->extractColor)($book->cover());

        if ($color !== $book->cover_color) {
            $book->cover_color = $color;
            $book->saveQuietly();
        }

        return $color;
    }

    /**
     * The book a piece of media belongs to, when it is one of its covers.
     */
    public function bookFor(Media $media): ?Book
    {
        if ($media->collection_name !== Book::COVERS_COLLECTION) {
            return null;
        }

        $book = $media->model;

        return $book instanceof Book ? $book : null;
    }
}
