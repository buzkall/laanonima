<?php

namespace App\Enums;

/**
 * What came of trying to fetch a book's cover after a save.
 *
 * Skipped and Failed both leave the book without a cover, but only one of them
 * is worth interrupting the bookseller over: a book catalogued by hand was
 * never going to get a cover, whereas a source that refused one is news.
 */
enum BookCoverOutcome
{
    /** A cover was downloaded and filed. */
    case Attached;

    /** Nothing to do: no source address, or the book already has images. */
    case Skipped;

    /** There was an address, and nothing usable came back from it. */
    case Failed;
}
