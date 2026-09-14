<?php

namespace App\Support\PublisherLogos;

/**
 * What Wikidata knows about one publisher.
 *
 * An item with no logotype is still an answer -- it usually carries the
 * publisher's website, and that is what the website fallback starts from -- so
 * a publisher identified without one comes back with `hasLogo()` false rather
 * than as null, which means nobody could tell which item this is.
 */
final readonly class WikidataPublisher
{
    public function __construct(
        public string $qid,
        public ?string $label = null,
        /** P856, exactly as the item records it -- often http://. */
        public ?string $website = null,
        /** "File:Norma Editorial.svg", or null when the item has no P154. */
        public ?string $file = null,
        /** The Commons thumbnail, which is a PNG even for an SVG file. */
        public ?string $imageUrl = null,
        public ?string $artist = null,
        public ?string $license = null,
        public ?string $licenseUrl = null,
        /** The Commons file page, never the thumbnail. */
        public ?string $sourceUrl = null,
    ) {}

    public function hasLogo(): bool
    {
        return filled($this->file) && filled($this->imageUrl);
    }
}
