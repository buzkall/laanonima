<?php

namespace App\Support\Portraits;

/**
 * What a source found out about one writer.
 *
 * A match with a null `$file` is not the same thing as no match at all: the
 * first says we identified the right person and nobody has photographed them,
 * the second says we could not tell who they are. The command records them as
 * different verdicts and only re-asks about one of them, so the distinction has
 * to survive the return.
 */
final readonly class PortraitMatch
{
    /**
     * @param  array<int, string>  $occupations  the P106 values that passed the guard
     */
    public function __construct(
        public ?string $qid,
        public ?string $label,
        public ?string $description,
        public array $occupations = [],
        /** "File:Stephen King, Comicon.jpg", or null when the item has no P18. */
        public ?string $file = null,
        public ?string $imageUrl = null,
        /** Plain text: Commons hands the artist over as an HTML fragment. */
        public ?string $artist = null,
        public ?string $license = null,
        public ?string $licenseUrl = null,
        public bool $attributionRequired = false,
        /** The Commons file page, never the thumbnail with its tracking parameters. */
        public ?string $sourceUrl = null,
    ) {}

    /** A person was identified and somebody has photographed them. */
    public function hasImage(): bool
    {
        return filled($this->file) && filled($this->imageUrl);
    }
}
