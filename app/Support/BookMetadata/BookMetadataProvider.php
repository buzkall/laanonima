<?php

namespace App\Support\BookMetadata;

interface BookMetadataProvider
{
    /**
     * Look one ISBN-13 up, or return null when this source does not know it.
     *
     * Implementations must not throw for a miss, a rate limit or a network
     * failure: a lookup that finds nothing is an ordinary outcome, and the
     * bookseller fills the form in by hand.
     */
    public function find(string $isbn13): ?BookMetadata;
}
