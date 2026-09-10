<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The share card
    |--------------------------------------------------------------------------
    |
    | A book, an author and an imprint each get a drawn 1200x630 card rather
    | than their bare cover: every scraper letterboxes a 2:3 portrait into a
    | 1.91:1 slot, so what a reader used to see in a chat thread was a
    | horizontal strip of the middle of a cover with no title on it.
    |
    | The card reproduces the page it advertises -- the cover color on the left,
    | the cream page on the right -- which is also what keeps it legible: the
    | title is set in the palette's accent, and CoverPalette defines that as the
    | cover color walked towards black until it clears 4.5:1 against the paper.
    | Contrast is therefore guaranteed for every book in the catalog rather than
    | hoped for.
    |
    */

    'width'  => 1200,
    'height' => 630,

    /*
     | At 80 a card of one flat color, a photograph and some type lands around
     | 100-180 KB, comfortably under the ~300 KB a chat client will preview.
     | Drop this before dropping the dimensions: a smaller card is cropped by
     | the scrapers, a softer one is only softer.
     */
    'quality' => 80,

    'disk' => 'og',

    /*
     | Bumped by hand when the drawing changes.
     |
     | The rest of the fingerprint is read off the record, so a retitled book or
     | a replaced cover redraws itself. A layout change is the one thing no data
     | can see, and without this line every card already on disk would keep the
     | old design forever.
     */
    'version' => 1,

    'assets' => [
        'font' => resource_path('fonts/Gloock-Regular.ttf'),

        /* The wordmark carries its own brand colors -- black, mint and magenta
           -- so it is pasted, never recolored, and it sits on the cream half
           where it was drawn to sit. */
        'wordmark' => resource_path('images/brand/la-anonima-logo.png'),

        /* Stands in for a cover we do not have. The same file config/qr.php
           puts in the middle of a code. */
        'isotipo' => resource_path('images/brand/interrogacion-mono.png'),
    ],

    /*
     | The split. The left band is the colored one and holds the picture; the
     | right is the cream page and holds the type.
     */
    'band'    => 480,
    'padding' => 56,

    /*
     | How many covers stand on a shelf's card, and how the ones behind are
     | drawn. No rotation: GD leaves a hairline of the fill color along every
     | edge of a rotated truecolor image, and axis-aligned reads as a stack of
     | books rather than as an accident.
     */
    'images' => [
        'max'    => 3,
        'scale'  => 0.78,
        'offset' => [30, 24],
    ],

    /*
     | The page's own two-layer ink shadow, as [offset_y, radius, opacity,
     | downscale]. GD's gaussian is a fixed 3x3 kernel applied over and over, so
     | a 60px radius at full size is a hundred passes over three quarters of a
     | million pixels. Each layer is blurred at a fraction of its size and
     | scaled back up instead; nothing in a chat thumbnail can tell.
     */
    'shadow' => [
        [2, 8, 0.18, 2],
        [26, 60, 0.34, 8],
    ],

    /*
     | Type. The title starts at "size" and steps down by "step" until it fits
     | "lines"; below "min" it is wrapped, cut to "lines" and given an ellipsis.
     */
    'title' => [
        'size'  => 72,
        'min'   => 40,
        'step'  => 4,
        'lines' => 3,
    ],

    /*
     | 22 rather than 24 because a two-author credit -- "DE ANA GARRIGA, CARMEN
     | URBITA" -- measures 633px tracked at 24 and only 587 at 22, against a
     | 608px column. The larger size cut a perfectly ordinary book's authors in
     | half. Longer credits than that are still trimmed.
     */
    'subtitle' => [
        'size'     => 22,
        'tracking' => 0.14,
    ],

    /*
     | Measured against a reference string that carries both an accent and a
     | descender, then reused for every line: deriving it per line from that
     | line's own box springs the block open wherever a line happens to have no
     | descender.
     */
    'line_height' => 1.16,

    'rule' => [
        'width'  => 2,
        'length' => 96,
        'gap'    => 30,
    ],

    'subtitle_gap' => 30,

    'wordmark_width' => 168,

];
