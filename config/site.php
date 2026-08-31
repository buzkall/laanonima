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
         | The red of the reference design, for a book with no cover to read.
         */
        'fallback' => '#e22314',

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
    ],

];
