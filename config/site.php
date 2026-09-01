<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shop
    |--------------------------------------------------------------------------
    |
    | Where a reader who wants a book writes to. The book page has no basket:
    | every call to action on it is a mailto to this address, so it is the one
    | thing on the page that must never be a placeholder in production.
    |
    */

    'contact_email' => env('SITE_CONTACT_EMAIL', 'hola@laanonima.es'),

    /*
    |--------------------------------------------------------------------------
    | Palette
    |--------------------------------------------------------------------------
    |
    | The design is one flat colour taken off a book's cover plus cream and
    | ink, so these are every colour on the site that is not derived from a
    | cover. CoverPalette reads them and works out the two that follow: what to
    | write on top of the cover colour, and how dark it has to get before it
    | reads as a link on the cream page.
    |
    | Careful with "paper" and "ink": Tailwind cannot read PHP, so the same two
    | values are declared again in the @theme block of resources/css/app.css as
    | --color-paper and --color-ink. Change one and change the other.
    |
    */

    'palette' => [

        /*
         | The house colour, for a book with no cover to read.
         */
        'fallback' => '#80d7ac',

        /*
         | The cream page and the ink written on it.
         */
        'paper' => '#f4efe4',
        'ink'   => '#211511',

        /*
         | Cream over a cover colour, a shade warmer than the page.
         */
        'cream' => '#f7f0e1',

        /*
         | Both derived colours are decided by contrast ratio rather than by a
         | lightness threshold, because the averaged colours the covers produce
         | land all over the place: a washed pink for one book, a near-black
         | brown for the next. 4.5:1 is WCAG AA for body text.
         |
         | The accent is the cover colour walked towards black, keeping this
         | much of each channel per step, until it clears the threshold against
         | the page. A larger step is a slower walk and a closer match to the
         | cover; a smaller one gets there in fewer passes and overshoots.
         */
        'min_contrast' => 4.5,
        'darken_step'  => 0.88,

    ],

    /*
    |--------------------------------------------------------------------------
    | Shelf
    |--------------------------------------------------------------------------
    |
    | How many books a public listing shows before a reader has to ask for the
    | next page. The home page, an author's page and a publisher's page all use
    | it, so the shelf is the same length wherever it is read.
    |
    */

    'shelf' => [
        'per_page' => 24,

        /*
         | How many books stand on /estanteria. That shelf is one row drawn to
         | scale rather than a paged grid, and every book on it is a rigid body
         | in the browser's physics loop, so the limit is what the row can carry
         | and not what a page can show.
         */
        'on_stage' => 24,

        /*
         | How many of them are turned face out, as a bookseller turns a couple
         | of covers towards the door and leaves the rest showing their spines.
         | Which two is decided per visit, along with the order.
         */
        'facing_out' => 2,

        /*
         | A shelf drawn to scale needs three measurements, and today most
         | records have none: the ISBN lookup only fills them in when the source
         | has them, which for Spanish editions is the minority. So a book with
         | nothing measured is stood up at the ordinary size for its binding
         | rather than left off the shelf.
         |
         | Width and height in millimetres, keyed by BookBinding value.
         */
        'sizes' => [
            'paperback'  => [140, 210],
            'hardback'   => [150, 230],
            'pocket'     => [110, 180],
            'board_book' => [160, 160],
            'spiral'     => [150, 210],
            'ebook'      => [140, 210],
            'audiobook'  => [140, 210],
            'default'    => [140, 210],
        ],

        /*
         | The spine when the thickness is not known: paper is about this thick
         | a leaf, plus the covers. A hardback's boards add a few millimetres
         | more. Both floors are there so a slim book still has a spine to read.
         */
        'mm_per_page'          => 0.055,
        'mm_for_covers'        => 2,
        'mm_for_boards'        => 4,
        'min_thickness_mm'     => 6,
        'default_thickness_mm' => 20,
    ],

];
