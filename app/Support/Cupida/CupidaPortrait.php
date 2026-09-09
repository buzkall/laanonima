<?php

namespace App\Support\Cupida;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The face on an author card, and who to credit for it.
 *
 * Built from a row of author-photos.json, but only ever by CupidaCatalog,
 * which is also the only thing that knows whether the JPEG is on this machine
 * at all. That check is not incidental: the bytes are not committed, so a
 * deploy that skipped `cupida:portraits:fetch` has the metadata and none of the
 * images, and a card must fall back to no face rather than to a broken one.
 */
final readonly class CupidaPortrait
{
    private function __construct(
        /** The file name on the portraits disk, e.g. "sastre-elvira.jpg". */
        public string $file,
        /** The tint that sits behind the photo while it loads. */
        public ?string $color,
        public ?string $artist,
        public ?string $license,
        public ?string $licenseUrl,
        public ?string $sourceUrl,
    ) {}

    /**
     * @param  array<string, mixed>|null  $entry  a row out of author-photos.json
     */
    public static function fromPool(?array $entry): ?self
    {
        if (! is_array($entry) || ($entry['status'] ?? null) !== 'matched') {
            return null;
        }

        $file = $entry['photo'] ?? null;

        /* A row that says "matched" and names no file is a corrupt row, not a
           face. It is the one thing worth checking here rather than trusting. */
        if (! is_string($file) || blank($file)) {
            return null;
        }

        return new self(
            file: $file,
            color: self::string($entry['color'] ?? null),
            artist: self::string(data_get($entry, 'credit.artist')),
            license: self::string(data_get($entry, 'credit.license')),
            licenseUrl: self::string(data_get($entry, 'credit.license_url')),
            sourceUrl: self::string(data_get($entry, 'credit.source_url')),
        );
    }

    /**
     * The photographer, short enough to sit under the deck.
     *
     * Commons' `Artist` field is free text and some of it is provenance rather
     * than a name -- Tolkien's portrait credits "Unknown photo studio
     * commissioned by Tolkien's students 1925/6 (private communication from
     * Catherine McIlwaine, Tolkien Archivist, Bodleian Library)", which is
     * three lines under the cards and drowns the line it is part of. The whole
     * value stays in author-photos.json, which is the record; this is what the
     * page has room for.
     */
    public function artistLabel(): ?string
    {
        return $this->artist === null ? null : Str::limit($this->artist, 60);
    }

    public function url(): string
    {
        return (string)Storage::disk(config('cupida.portraits.disk'))->url($this->file);
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && filled($value) ? $value : null;
    }
}
