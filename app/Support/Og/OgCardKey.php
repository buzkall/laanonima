<?php

namespace App\Support\Og;

use App\Models\Author;
use App\Models\Book;
use App\Models\Publisher;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stringable;

/**
 * Which card, and what it should look like -- without drawing any of it.
 *
 * A page needs a URL for its og:image on every render, so this half reads
 * columns and the media relation the controller already eager-loaded and
 * nothing else. Decoding images is OgCard's job, and only happens when a card
 * actually has to be drawn.
 *
 * The fingerprint is what makes the whole scheme work: it is derived from the
 * record rather than stored beside it, so a retitled book, a replaced cover or
 * a hand-picked color simply produces a different filename. Nothing has to
 * listen for a change and nothing can go stale -- which is the trap
 * SyncCoverColor's docblock is a monument to.
 */
final readonly class OgCardKey implements Stringable
{
    private function __construct(
        private string $folder,
        private int $id,
        private string $fingerprint,
    ) {}

    public static function forBook(Book $book): self
    {
        $cover = $book->cover();

        return new self('libro', (int)$book->id, self::hash([
            'libro',
            $book->id,
            $book->title,
            (string)$book->authors_line,
            (string)$book->cover_color,
            self::stamp($cover),
        ]));
    }

    public static function forAuthor(Author $author, int $bookCount, ?string $shelfStamp = null): self
    {
        return new self('autor', (int)$author->id, self::hash([
            'autor',
            $author->id,
            $author->name,
            $bookCount,
            (string)$shelfStamp,
        ]));
    }

    public static function forPublisher(Publisher $publisher, int $bookCount, ?string $shelfStamp = null): self
    {
        return new self('editorial', (int)$publisher->id, self::hash([
            'editorial',
            $publisher->id,
            $publisher->name,
            $bookCount,
            self::stamp($publisher->getFirstMedia(Publisher::LOGO_COLLECTION)),
            (string)$shelfStamp,
        ]));
    }

    /**
     * Where the card is filed. The fingerprint is in the name, so a card that
     * would look different is a different file and the old one is simply never
     * asked for again.
     */
    public function path(): string
    {
        return "{$this->folder}/{$this->id}-{$this->fingerprint}.jpg";
    }

    /**
     * Everything ever filed for this record, so superseded cards can be swept
     * the moment a new one is written.
     */
    public function pattern(): string
    {
        return "{$this->folder}/{$this->id}-";
    }

    public function folder(): string
    {
        return $this->folder;
    }

    /**
     * Absolute, which is what every scraper requires of an og:image.
     */
    public function url(): string
    {
        return rtrim((string)config('filesystems.disks.og.url'), '/') . '/' . $this->path();
    }

    public function __toString(): string
    {
        return $this->url();
    }

    /**
     * @param  list<string|int|null>  $parts
     */
    private static function hash(array $parts): string
    {
        /* The layout version rides along because it is the one thing no column
           can tell us: without it, a change to the drawing would leave every
           card already on disk frozen at the old design forever. */
        $parts[] = (int)config('og.version');

        return substr(sha1(implode('|', array_map(strval(...), $parts))), 0, 12);
    }

    private static function stamp(?Media $media): string
    {
        if (! $media instanceof Media) {
            return '';
        }

        return $media->id . ':' . $media->updated_at?->getTimestamp();
    }
}
