<?php

namespace App\Support\Portraits;

interface PortraitSource
{
    /**
     * Find a portrait for this name, or return null when this source has none.
     *
     * Implementations must not throw for a miss, a rate limit or a network
     * failure: a writer nobody has photographed is the ordinary outcome, and
     * the card is perfectly good without a face.
     *
     * The two optional arguments are the hand-correction path. `$qid` pins the
     * Wikidata item, skipping the search that chose the wrong person; `$file`
     * pins the Commons file, skipping Wikidata entirely for the case no guard
     * can catch -- the right person whose only photo is a statue, a book cover
     * or a group shot.
     */
    public function find(string $name, ?string $qid = null, ?string $file = null): ?PortraitMatch;
}
