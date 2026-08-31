<?php

namespace App\Actions\Books;

use App\Models\Book;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Give a book with no colour of its own the one its cover leads with.
 *
 * books.cover_color belongs to whoever wrote it: reading it off the cover is
 * how an empty column gets filled, and nothing more. A colour already on the
 * record -- derived once, or chosen in the panel, the two are not told apart --
 * survives every later upload, reordering and deletion. Emptying the field is
 * what asks for it to be read again.
 *
 * It cannot be derived while the book is being saved: media library attaches a
 * cover *after* the model is written, so a saving hook only ever sees the state
 * before the cover arrived. The trigger is therefore the media itself — added,
 * deleted, or reordered — wired up in AppServiceProvider.
 */
class SyncCoverColor
{
    public function __construct(private ExtractCoverColor $extractColor) {}

    /**
     * @return string|null the colour now stored
     */
    public function __invoke(Book $book): ?string
    {
        /*
         | Everything about the instance the event hands over is stale: the
         | attributes were read before the form wrote the record, and the media
         | that triggered this was attached afterwards. Reading the colour off
         | the object rather than off the row is how a colour just emptied in
         | the panel looks like a colour still there.
         |
         | Nothing comes back when the book itself is on its way out, taking its
         | media with it.
         */
        $book = $book->fresh('media');

        if (! $book instanceof Book) {
            return null;
        }

        if (filled($book->cover_color)) {
            return $book->cover_color;
        }

        $color = ($this->extractColor)($book->cover());

        if ($color !== null) {
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
