<?php

namespace App\Support\Cupida;

use App\Support\CoverPalette;

/**
 * One card in the deck.
 *
 * A card is whatever a reader can say yes or no to -- a subject, an author, a
 * mood -- flattened to the same fields so the deck, the view and the
 * scoring never have to ask which of the three they are holding. `kind` is only
 * read when the answers are turned into a shortlist, where a liked author and a
 * liked subject do mean different things.
 *
 * It carries its own palette for the same reason a book card does: every card
 * is painted in one flat color with cream or ink over it, whichever reads
 * better, and that decision belongs to the card rather than to the view.
 */
final readonly class CupidaCard
{
    public function __construct(
        /** theme, author or mood. */
        public string $kind,
        /** Unique within a session: a subject code, an author slug, a mood key. */
        public string $key,
        public string $label,
        /** The small line under the label -- a stocked count, a title, nothing. */
        public ?string $note,
        public CoverPalette $palette,
        /** Only an author card ever carries a face, and not all of those do. */
        public ?CupidaPortrait $portrait = null,
    ) {}

    public static function make(
        string $kind,
        string $key,
        string $label,
        ?string $note,
        string $color,
        ?CupidaPortrait $portrait = null,
    ): self {
        return new self(
            kind: $kind,
            key: $key,
            label: $label,
            note: $note,
            palette: CoverPalette::fromCover($color),
            portrait: $portrait,
        );
    }

    /**
     * What the swipe is recorded as. The kind travels with the key because the
     * component stores one flat list of likes and passes, and "FM" on its own
     * cannot be told from an author slug.
     */
    public function answer(): string
    {
        return "{$this->kind}:{$this->key}";
    }
}
