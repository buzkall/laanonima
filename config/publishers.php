<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Logotypes
    |--------------------------------------------------------------------------
    |
    | `publishers:logos` and the "Find logotype" row action look a publisher
    | up on Wikidata first and fall back to the icon its own website declares.
    | See App\Actions\Publishers\FetchPublisherLogo.
    |
    */

    'logos' => [
        /*
         | Wikimedia answers an unidentified client with a 403, so this names
         | the project and carries a contact address -- the same string La
         | Cupida's portraits send, unless it is set on its own.
         */
        'user_agent' => env('WIKIMEDIA_USER_AGENT', env(
            'CUPIDA_WIKIMEDIA_USER_AGENT',
            'LaAnonima/1.0 (https://laanonimalibreria.com; contacto@laanonimalibreria.com)',
        )),

        'timeout'  => 10,
        'delay_ms' => 400,

        /* How many search candidates to weigh before trying the next spelling. */
        'search_limit' => 7,

        /*
         | P31 values that make an item a publisher. Searching "Taurus" returns a
         | constellation, a cruise missile and a rocket before the imprint, and
         | "Salamandra" an amphibian and a speed metal band.
         */
        'publisher_types' => [
            'Q2085381', // publishing house
            'Q1320047', // book publisher
            'Q1114515', // comics publishing company
            'Q2608849', // imprint
        ],

        /*
         | Stripped off the end of a name before searching again: Wikidata finds
         | nothing at all for "Norma Editorial, S.A.". Compared without periods.
         */
        'legal_forms' => ['sa', 'sau', 'sl', 'slu', 'sll', 'scoop', 'sccl', 'cb', 'ltd', 'inc', 'srl', 'gmbh'],

        /*
         | One of these is dropped from the start or the end of a name as a last
         | spelling to try: "Ediciones La Cúpula" is filed as "La Cúpula".
         | Longest first, so the phrase goes before the word inside it.
         */
        'generic_words' => ['edición de libros', 'ediciones', 'edicions', 'editorial', 'editions', 'publishing', 'libros', 'books'],

        /* Commons serves an SVG logotype rasterised, so a thumbnail is always asked for. */
        'thumb_width' => 800,

        /*
         | Not trusted input: the thumbnail URL is read out of an API response.
         | https only, these hosts only, re-checked on every redirect hop.
         */
        'allowed_hosts' => [
            'upload.wikimedia.org',
            '*.wikimedia.org',
        ],

        'website' => [
            'enabled' => true,
            'timeout' => 8,

            'max_html_bytes' => 1024 * 1024,

            /* Icons tried in order before giving up on a site. */
            'max_candidates' => 3,

            /*
             | Hosts that belong to a group rather than to one imprint. Wikidata
             | files Debolsillo's website as Penguin Random House's, and its icon
             | is the group's, not the imprint's. The link is still filled in; the
             | icon is never taken from these.
             */
            'shared_hosts' => [
                'penguinlibros.com',
                '*.penguinlibros.com',
                'penguinrandomhousegrupoeditorial.com',
                '*.penguinrandomhousegrupoeditorial.com',
                'planetadelibros.com',
                '*.planetadelibros.com',
                'grupoanaya.es',
                '*.grupoanaya.es',
            ],
        ],

        'max_bytes' => 3 * 1024 * 1024,

        /*
         | Below this a logo rendered 72px high is mush. The short side is
         | allowed to be much smaller: a wordmark is legitimately wide and low.
         */
        'min_long_side'  => 120,
        'min_short_side' => 24,

        /* Stored no larger than this; the pages render it 72px high. */
        'max_width'  => 800,
        'max_height' => 800,
    ],

];
