<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Book metadata lookup
    |--------------------------------------------------------------------------
    |
    | Providers are tried in order and their results merged field by field, so a
    | later provider can fill gaps an earlier one left (a cover, a synopsis).
    |
    | Open Library comes first because it needs no credentials. Google Books is
    | skipped entirely until GOOGLE_BOOKS_API_KEY is set: its unauthenticated
    | quota is shared and routinely exhausted, so keyless calls just 429.
    |
    | Casa del Libro answers with a cover and nothing else, and sits ahead of
    | Google Books because of it: the first source with a cover wins, and Google
    | publishes none worth having for a Spanish edition (see the provider). It
    | earns its place twice over -- openlibrary.org goes down for days at a time
    | (it was refusing connections outright while this was written), which
    | otherwise leaves the lookup with no cover source at all.
    |
    | When DILVE credentials arrive, register a "dilve" provider and put it
    | first. Nothing else has to change.
    |
    */

    'metadata' => [

        'providers' => ['open_library', 'casa_del_libro', 'google_books'],

        'cache_ttl' => 60 * 60 * 24,

        /*
         | A miss is cached too, so retyping an ISBN no source knows is not a
         | round trip every time -- but for minutes, not for a day. A miss is
         | not always the truth: while a source is down every ISBN looks like
         | one, and a day-long entry would keep the lookup empty long after the
         | source came back.
         */
        'miss_cache_ttl' => 60 * 10,

        'timeout' => 5,

        /*
         | Seconds a book filed off the Cupida pool gets to be enriched, once
         | the reader's response has already gone out. It restarts the clock
         | rather than adding to it: the request that dispatched it has usually
         | already spent a while waiting on a model, and what is left of the
         | default thirty does not cover this.
         |
         | Sized for the bad afternoon, not the good one. A lookup normally
         | costs a second or two, but every provider retries twice, so a host
         | that is unreachable rather than merely slow costs `timeout` x 3 each
         | -- four calls plus the cover download is a bit over seventy-five
         | seconds, and going over is a fatal in a terminating callback rather
         | than a warning. Raise this if a provider is added.
         */
        'enrich_time_limit' => 120,

        /*
         | Open Library asks API consumers to identify themselves, and grants a
         | higher rate limit to those who do.
         */
        'user_agent' => env('BOOK_METADATA_USER_AGENT', 'LaAnonima/1.0 (+https://laanonimalibreria.arzcode.com)'),

        'google_books' => [
            'key'     => env('GOOGLE_BOOKS_API_KEY'),
            'country' => env('GOOGLE_BOOKS_COUNTRY', 'ES'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Covers
    |--------------------------------------------------------------------------
    |
    | Books and publishers keep their images in spatie/laravel-medialibrary, so
    | nothing here decides where a file ends up. What is left is the rules a
    | downloaded cover has to satisfy before it is attached.
    |
    */

    'covers' => [
        'max_bytes' => 5 * 1024 * 1024,

        /*
         | Where the seeder looks for a cover it downloaded on an earlier run,
         | so re-seeding does not re-fetch eight images -- and works offline.
         | Files found here are copied into the media library, not moved.
         */
        'seed_disk'      => 'public',
        'seed_directory' => 'covers',

        /*
         | Sources answer a miss with a "no image" placeholder and a 200 rather
         | than a 404, so the status code proves nothing and a floor is what
         | rejects them.
         |
         | Keep it low. It is there to catch blank and one-pixel placeholders,
         | not to judge quality: Open Library's "-L" is frequently around
         | 230x350 for older scans, and an earlier 400x600 floor silently threw
         | away real covers. A small cover beats no cover -- the bookseller can
         | see it and replace it, which is not true of one that never arrived.
         */
        'min_width'  => 200,
        'min_height' => 300,

        /*
         | Covers are shown a few hundred pixels wide, so the 2000px originals
         | the sources hand over are stored downscaled. Everything is re-encoded
         | to JPEG: some sources still serve 256-color GIF.
         */
        'max_width'  => 800,
        'max_height' => 1200,
        'quality'    => 85,

        /*
         | Cover URLs are not trusted input: they arrive inside third-party API
         | responses, so a provider that is compromised, spoofed or merely wrong
         | could otherwise point the server at an internal address.
         |
         | Only these hosts are fetched, only over https, and every redirect hop
         | is checked against the same list. Open Library needs the archive.org
         | entries: it answers a cover with a 302 onto ia*.us.archive.org.
         */
        'allowed_hosts' => [
            'covers.openlibrary.org',
            'archive.org',
            '*.archive.org',
            'books.google.com',
            '*.googleusercontent.com',
            '*.casadellibro.com',
            /*
             | Our own shop, and the one URL on this list we construct rather
             | than read out of somebody's API response. It is the last resort
             | for a book filed off the Cupida pool: the shop resizes on demand
             | for every EAN it stocks, so it has a cover where the free ISBN
             | sources have none, which is about one Spanish book in six.
             */
            'laanonimalibreria.com',
        ],
    ],

];
