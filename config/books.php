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
    | When DILVE credentials arrive, register a "dilve" provider and put it
    | first. Nothing else has to change.
    |
    */

    'metadata' => [

        'providers' => ['open_library', 'google_books'],

        'cache_ttl' => 60 * 60 * 24,

        'timeout' => 5,

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
         | than a 404, and those placeholders are cover-shaped: cegal's is
         | 250x375. A floor rejects them along with the thumbnails that are too
         | small to put in front of a customer.
         */
        'min_width'  => 400,
        'min_height' => 600,

        /*
         | Covers are shown a few hundred pixels wide, so the 2000px originals
         | the sources hand over are stored downscaled. Everything is re-encoded
         | to JPEG: some sources still serve 256-colour GIF.
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
        ],
    ],

];
