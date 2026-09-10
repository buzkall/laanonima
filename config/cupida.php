<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The deck
    |--------------------------------------------------------------------------
    |
    | La Cupida asks three questions and each is answered by swiping a small
    | deck. Three rounds of six is eighteen swipes, which is about as long as
    | anyone will stand on a bus before they want the answer; a longer deck
    | reads as a form rather than as a game.
    |
    | Nothing is drawn twice within a session -- an author already passed over
    | in round two never comes back -- so the deck size also puts a floor under
    | how much catalog the JSON pool has to carry.
    |
    */

    'deck' => [
        'size' => 6,

        /*
         | The fewest books in the pool a subject card is worth dealing.
         |
         | A card promising "Fantasía romántica" is a promise about the shelf
         | behind it, and the shortlist can only offer what the pool holds --
         | so a subject that reaches this is dealt and one that does not is
         | dropped, silently and by itself. It is the reason `subjects` below
         | can be a long list written once rather than a list pruned by hand
         | every time the stock moves: a subject the shop sells out of stops
         | being asked about, and comes back when it is restocked.
         */
        'min_books' => 20,

        /*
         | The fewest distinct titles by one writer the authors round will deal.
         |
         | A flat shuffle of every author the shop lists is a deck of six
         | writers nobody has heard of: two thousand seven hundred of the three
         | and a half thousand names in the pool have exactly one book. So there
         | has to be a floor, and this is it.
         |
         | It used to be a rank cut -- the best-stocked hundred and fifty names
         | -- and a rank cut falls where it falls. The boundary landed in the
         | middle of the four-book tie, which the scrape breaks alphabetically,
         | so the last fifth of the pool was the four-book writers whose name
         | sorts before "Masashi" and the ones after it could never be dealt at
         | all. A floor on the books has no such inside and outside.
         |
         | Two rather than four is a trade, and it is worth naming: six drawn
         | out of a hundred and fifty is a writer repeated about every fourth
         | session, which a reader who plays twice in a week notices, and six
         | out of seven hundred is not. The price is that the pool reaches
         | further down the shelf and more of the cards are names to be met
         | rather than recognized.
         |
         | It counts titles, never copies. Two editions of one novel are one
         | book here -- see `ScrapeCupidaCatalog::authors()`.
         */
        'author_min_books' => 2,

        /*
         | Every card is painted like a book card: one flat color with cream or
         | ink written over it, whichever CoverPalette decides reads better. The
         | house fallback in site.palette is deliberately not in this list --
         | that green means "book with no cover", and it would be confusing to
         | meet it here as a deliberate choice.
         */
        'colors' => [
            '#fa008a',
            '#80d7ac',
            '#f2b705',
            '#3d5afe',
            '#e34a33',
            '#7b4bd6',
            '#0f8b8d',
            '#d64550',
        ],

        /*
         | The subject cards, as THEMA codes with the label a reader sees.
         |
         | Three things are going on here, and each is why the list looks the
         | way it does.
         |
         | The labels are ours. The shop's own headings are the standard's
         | headings -- "Ficción y temas afines", "Crimen, delito y misterio:
         | procedimientos policiales" -- which are accurate, bureaucratic and
         | unswipeable.
         |
         | The list is much longer than the deck, the way `moods` is. Six drawn
         | out of eighteen is a third of the list every session, so two readers
         | a week apart met nearly the same six questions; six out of fifty is
         | a different first round each time. The width is also what lets a
         | card be narrow -- "Manga", "Historia militar", "Diarios y cartas" --
         | which is a bad question to ask everyone and a good one to meet once.
         |
         | A code deeper than its neighbors is deliberate and load-bearing.
         | `CupidaShortlist::matchesSubject()` matches a card against the books
         | filed at or below it, so "Manga" (XAM) reaches the manga and not the
         | other four hundred comics filed at bare X -- and both are cards. A
         | narrow code only works because the broad one is still in the list to
         | catch everything else.
         |
         | Order is irrelevant: the deck shuffles. Codes are grouped by branch
         | so a reader of this file can see what is covered and what is not.
         */
        'subjects' => [
            // Ficción
            'FB'  => 'Narrativa',
            'FBC' => 'Clásicos',
            'FD'  => 'Ficción especulativa',
            'FDB' => 'Distopías',
            'FF'  => 'Crimen y misterio',
            'FFD' => 'Detectives',
            'FFJ' => 'Misterio amable',
            'FFL' => 'Novela negra',
            'FK'  => 'Terror',
            'FKC' => 'Terror clásico y fantasmas',
            'FL'  => 'Ciencia ficción',
            'FM'  => 'Fantasía',
            'FMB' => 'Fantasía épica',
            'FMM' => 'Realismo mágico',
            'FMR' => 'Fantasía romántica',
            'FQ'  => 'Vidas contemporáneas',
            'FV'  => 'Novela histórica',

            // Poesía, ensayo y no ficción
            'DC'   => 'Poesía',
            'DN'   => 'Biografías y memorias',
            'DNBF' => 'Vidas de artistas',
            'DNBL' => 'Vidas de escritores',
            'DND'  => 'Diarios y cartas',
            'DNL'  => 'Ensayo literario',
            'DNP'  => 'Periodismo y crónica',
            'DNX'  => 'Historias reales',

            // Cómic y novela gráfica
            'X'   => 'Cómic y novela gráfica',
            'XAD' => 'Cómic europeo',
            'XAK' => 'Cómic americano',
            'XAM' => 'Manga',
            'XQA' => 'Cómic autobiográfico',
            'XQT' => 'Cómic de humor',

            // Sociedad
            'JB'   => 'Sociedad',
            'JBC'  => 'Cultura y medios',
            'JBSF' => 'Feminismos',
            'JBSJ' => 'LGTBIQ+',

            // Historia
            'NH'   => 'Historia',
            'NHC'  => 'Historia antigua',
            'NHD'  => 'Historia de Europa',
            'NHTB' => 'Historia social',
            'NHW'  => 'Historia militar',

            // Filosofía
            'QD'   => 'Filosofía',
            'QDTS' => 'Filosofía política',
            'QDX'  => 'Filosofía para la vida',

            // Arte, cocina, infantil
            'AG'  => 'Arte',
            'AGA' => 'Historia del arte',
            'WB'  => 'Cocina',
            'YF'  => 'Infantil y juvenil',
            'YFC' => 'Aventuras (infantil)',
            'YFH' => 'Fantasía infantil',
            'YFQ' => 'Humor infantil',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | The author portraits
    |--------------------------------------------------------------------------
    |
    | A name is a weak thing to recognize in the second and a half a swipe
    | lasts, so the author cards carry a face where we have one. They come from
    | Wikidata (which item is this writer) and Wikimedia Commons (their photo
    | and its license), resolved by hand with `cupida:portraits:resolve` and
    | recorded in resources/data/cupida/author-photos.json.
    |
    | Only the metadata file is committed. The JPEGs are downloaded into the
    | environment by `cupida:portraits:fetch` on deploy, and a machine that has
    | not run it deals the same faceless cards the page dealt before any of this
    | existed -- never a broken image.
    |
    */

    'portraits' => [
        /*
         | Wikimedia answers an unidentified client with a 403, so this must
         | name the project and carry a contact address.
         |
         | It is the exact opposite of cupida.scrape.user_agent, which is a
         | browser string precisely so the shop's challenge page lets it
         | through. Two keys that look alike and mean opposite things: do not
         | copy one over the other.
         */
        'user_agent' => env(
            'CUPIDA_WIKIMEDIA_USER_AGENT',
            'LaAnonimaCupida/1.0 (https://laanonimalibreria.com; contacto@laanonimalibreria.com)',
        ),

        'timeout'  => 15,
        'delay_ms' => 400,

        /* How many search candidates to weigh before giving up on a name. */
        'search_limit' => 7,

        /* P31: a candidate that is not a human is not somebody we can photograph. */
        'human' => 'Q5',

        /*
         | P106. This is the only thing standing between a card and the wrong
         | face, and it is worth the coverage it costs.
         |
         | Measured over the whole 150-name pool: 118 photos with the guard on.
         | Over a 19-name sub-sample checked by hand, without it 13 photos of
         | which two were the wrong person -- "Mary Oliver" resolved to a Dutch
         | jazz singer and "Michael McDowell" to an Irish politician. With it,
         | eleven photos and none wrong: both of those resolve to the right
         | writer, who happens to have no photo, and a blank card is the honest
         | answer. Collectives and pseudonyms fall out here too.
         */
        'occupations' => [
            'Q36180',    // writer
            'Q6625963',  // novelist
            'Q49757',    // poet
            'Q1930187',  // journalist
            'Q28389',    // screenwriter
            'Q214917',   // playwright
            'Q4853732',  // children's writer
            'Q1114448',  // cartoonist
            'Q10297252', // illustrator
            'Q11774202', // essayist
            'Q482980',   // author
            'Q18939491', // comics artist
            'Q715301',   // comics writer
            'Q201788',   // historian
            'Q4964182',  // philosopher
            'Q333634',   // translator
            'Q3391743',  // visual artist
        ],

        /*
         | The shop files an anthology under an author name, so the pool carries
         | things that are not people. "¿Te gusta Vv. Aa.?" is a card nobody can
         | answer, so these never reach the deck at all.
         |
         | Matched with Str::is against the folded name before any request is
         | made, because this is a shop-ism that recurs under a new spelling
         | every time the catalog grows. A shared pen name with a real byline
         | -- Carmen Mola is three writers, Beka is two -- no pattern can
         | express: those are marked by hand with `--none`.
         */
        'collective_patterns' => [
            'vv*aa*',
            'v.v.a.a*',
            'varios autores*',
            'autores varios*',
            'anonimo',
            '*et al*',
        ],

        /* Commons originals run to several megabytes. Always ask for a thumbnail. */
        'thumb_width' => 800,

        'disk' => 'portraits',

        /*
         | Not trusted input: the thumbnail URL is read out of an API response,
         | so it gets the treatment a cover URL gets. https only, these hosts
         | only, re-checked on every redirect hop.
         */
        'allowed_hosts' => [
            'upload.wikimedia.org',
            'commons.wikimedia.org',
            '*.wikimedia.org',
        ],

        'max_bytes' => 5 * 1024 * 1024,

        /* Below this there is no face left to crop out. */
        'min_width'  => 240,
        'min_height' => 240,

        /*
         | The card is aspect-[3/4] at about 420px and the portrait takes 58% of
         | its width, so 480 covers a 2x display with nothing upscaling. Portrait
         | rather than square because faces read better in 3:4 and it echoes the
         | card it sits on.
         */
        'width'   => 480,
        'height'  => 640,
        'quality' => 82,

        /*
         | How far down the source to take the crop from, as a fraction of the
         | slack. A Commons portrait is usually head-and-shoulders in the upper
         | half, and a centered crop decapitates a full-body shot.
         */
        'crop_anchor' => 0.15,
    ],

    /*
    |--------------------------------------------------------------------------
    | The mood deck
    |--------------------------------------------------------------------------
    |
    | The third question is the only one that is not catalog. What a reader
    | swipes here is a feeling, and a feeling is not a THEMA code, so each mood
    | carries the words that stand in for it: they are matched against a book's
    | title and synopsis when the shortlist is scored.
    |
    | The list is much longer than the six cards a session deals, on purpose.
    | Six drawn out of ten is most of the list every time and two readers meet
    | nearly the same question; six out of nearly forty is a question that
    | belongs to the session it was dealt in. It also means a mood can be
    | narrow -- "que dé hambre" is a bad card to have to deal every time and a
    | good one to meet once.
    |
    | The card a reader actually reads is in lang/es/cupida.php under
    | `moods.<key>`; only the matching lives here, and the icon the card is
    | drawn with is in `mood_icons` below. Add a mood in all three places or
    | TranslationsTest and CupidaDeckTest will say so.
    |
    | Keywords are matched without accents or case, so "corazon" catches
    | "corazón". Prefer stems -- "amist" reaches amistad and amistades.
    |
    */

    'moods' => [
        'heartbreak'  => ['duelo', 'perdida', 'muerte', 'ausencia', 'luto', 'adios', 'corazon', 'melancol'],
        'laugh'       => ['humor', 'divertid', 'comed', 'absurd', 'ironi', 'satir', 'risa'],
        'think'       => ['ensayo', 'filosof', 'pensamiento', 'ideas', 'critic', 'teoria'],
        'escape'      => ['viaje', 'aventura', 'lejos', 'isla', 'mar', 'expedicion', 'frontera', 'desierto'],
        'short'       => ['relatos', 'cuentos', 'breve', 'aforismo', 'poemas'],
        'doorstop'    => ['saga', 'epopeya', 'generacion', 'trilogia', 'monumental'],
        'rage'        => ['injusticia', 'lucha', 'resistencia', 'denuncia', 'dictadura', 'clase', 'violencia'],
        'comfort'     => ['amist', 'ternura', 'infancia', 'casa', 'consuelo', 'cuidado'],
        'true_story'  => ['memorias', 'biografi', 'cronica', 'testimonio', 'diario', 'real', 'periodis'],
        'strange'     => ['extrañ', 'onirico', 'surreal', 'fantasm', 'inquietante', 'raro', 'delirante'],
        'love'        => ['amor', 'enamora', 'romanc', 'pasion', 'deseo', 'seduccion'],
        'mystery'     => ['crimen', 'asesinat', 'misterio', 'investiga', 'detectiv', 'intriga', 'sospech'],
        'fear'        => ['terror', 'miedo', 'horror', 'pesadilla', 'siniestr', 'tenebros'],
        'future'      => ['ciencia ficcion', 'futuro', 'distop', 'utop', 'robot', 'apocalip', 'planeta'],
        'past'        => ['historic', 'siglo', 'medieval', 'imperio', 'posguerra', 'antigued'],
        'learn'       => ['divulga', 'aprend', 'explica', 'manual', 'introduccion', 'curios'],
        'poetry'      => ['poesia', 'poema', 'verso', 'lirica', 'poetic'],
        'women'       => ['feminis', 'mujer', 'patriarc', 'genero', 'sororidad', 'maternidad'],
        'queer'       => ['lesbian', 'queer', 'lgtb', 'trans', 'homosexual', 'disidencia'],
        'family'      => ['familia', 'madre', 'padre', 'herman', 'hijo', 'abuel'],
        'city'        => ['ciudad', 'barrio', 'urban', 'calle', 'metropoli', 'vecin'],
        'nature'      => ['naturaleza', 'bosque', 'montañ', 'animal', 'campo', 'paisaje', 'ecolog'],
        'slow'        => ['cotidian', 'calma', 'silencio', 'intim', 'sencill', 'lentitud'],
        'intense'     => ['trepidante', 'adictiv', 'tension', 'vertigo', 'suspense', 'ritmo'],
        'hope'        => ['esperanza', 'luminos', 'celebra', 'renacer', 'vitalidad', 'alegria'],
        'dark'        => ['oscur', 'sordid', 'brutal', 'crudo', 'noir', 'abismo'],
        'art'         => ['arte', 'musica', 'pintura', 'cine', 'fotograf', 'artista'],
        'politics'    => ['politic', 'democracia', 'capitalis', 'revolucion', 'poder', 'estado'],
        'body'        => ['cuerpo', 'enfermedad', 'salud', 'sexual', 'medicina', 'vejez'],
        'mind'        => ['ansiedad', 'depresion', 'locura', 'psicolog', 'mente', 'terapia'],
        'food'        => ['cocina', 'gastronom', 'comida', 'receta', 'vino', 'mesa'],
        'work'        => ['trabajo', 'oficina', 'precari', 'dinero', 'obrer', 'economia'],
        'identity'    => ['identidad', 'raices', 'exilio', 'migra', 'pertenencia', 'desarraigo'],
        'latin'       => ['latinoameric', 'argentin', 'mexic', 'chilen', 'colombian', 'caribe'],
        'world'       => ['japon', 'corean', 'nordic', 'africa', 'arabe', 'chino'],
        'illustrated' => ['ilustrad', 'comic', 'novela grafica', 'dibujo', 'viñet', 'album'],
        'classic'     => ['clasico', 'canon', 'obra maestra', 'universal', 'imprescindible'],
    ],

    /*
     | The picture on a mood card.
     |
     | A theme card carries its stocked count and an author card a face, and a
     | mood card carried nothing: a heading on a flat color, with the room the
     | portrait takes in round two standing empty. An outline Heroicon fills
     | that room, one per mood, named here without its `heroicon-o-` prefix so
     | the deck can hand the name straight to `svg()`. CupidaDeckTest checks
     | that every mood has one and that every name resolves to a file.
     */

    'mood_icons' => [
        'heartbreak'  => 'face-frown',
        'laugh'       => 'face-smile',
        'think'       => 'light-bulb',
        'escape'      => 'paper-airplane',
        'short'       => 'clock',
        'doorstop'    => 'calendar-days',
        'rage'        => 'fire',
        'comfort'     => 'home',
        'true_story'  => 'newspaper',
        'strange'     => 'cube-transparent',
        'love'        => 'heart',
        'mystery'     => 'magnifying-glass',
        'fear'        => 'exclamation-triangle',
        'future'      => 'rocket-launch',
        'past'        => 'building-library',
        'learn'       => 'academic-cap',
        'poetry'      => 'musical-note',
        'women'       => 'user-circle',
        'queer'       => 'flag',
        'family'      => 'user-group',
        'city'        => 'building-office-2',
        'nature'      => 'sun',
        'slow'        => 'pause',
        'intense'     => 'lifebuoy',
        'hope'        => 'sparkles',
        'dark'        => 'moon',
        'art'         => 'paint-brush',
        'politics'    => 'megaphone',
        'body'        => 'finger-print',
        'mind'        => 'puzzle-piece',
        'food'        => 'cake',
        'work'        => 'briefcase',
        'identity'    => 'map-pin',
        'latin'       => 'globe-americas',
        'world'       => 'globe-asia-australia',
        'illustrated' => 'pencil',
        'classic'     => 'film',
    ],

    /*
    |--------------------------------------------------------------------------
    | The recommendation
    |--------------------------------------------------------------------------
    |
    | The model never chooses out of the whole catalog. The pool is scored
    | against what the reader swiped and only the best `shortlist` books are
    | sent, with their EANs pinned into the response schema as an enum -- so a
    | recommendation is always a book the shop actually stocks, and the model's
    | job is narrowed to the one thing it is good at: picking one and saying
    | why.
    |
    | Keep the shortlist small enough that the whole prompt stays cheap and
    | large enough that two readers with the same taste do not always land on
    | the same book.
    |
    */

    'shortlist' => 30,

    /*
     | How many books by one author the shortlist lets through.
     |
     | A liked author is the heaviest weight in the scoring, so without a cap
     | three names said yes to in round two fill the thirty with three backlists
     | and the model is choosing between editions of the same taste. Enough of
     | a liked author to be recommended one of their books; not enough to crowd
     | out everybody else.
     */
    'shortlist_per_author' => 3,

    /*
     | Which model writes the pitch. Two sentences of warm Spanish does not need
     | a big model, and the demo is judged on how fast the answer arrives.
     */
    'model' => env('CUPIDA_MODEL', 'claude-haiku-4-5'),

    'timeout' => 30,

    /*
     | What a written recommendation costs, in dollars per million tokens.
     |
     | The provider hands back token counts and nothing else -- there is no
     | price anywhere in the response -- so the only way to put a figure in
     | front of the bookseller is to keep the rates here and multiply. Which
     | means this list goes stale silently: when the column stops matching the
     | invoice, this is what is wrong, not the arithmetic.
     |
     | Cache rates are Anthropic's multiples of the input rate (a write costs
     | 1.25x, a read 0.1x). Nothing here caches anything today, so they are
     | zero in practice and kept so a future prompt that does cache is priced
     | rather than mispriced.
     |
     | A model with no entry is recorded with its tokens and no cost. An empty
     | column says "we do not know what this cost"; a zero would say it was
     | free.
     */
    'prices' => [
        'claude-haiku-4-5' => [
            'input'       => 1.00,
            'output'      => 5.00,
            'cache_write' => 1.25,
            'cache_read'  => 0.10,
        ],
        'claude-sonnet-5' => [
            'input'       => 2.00,
            'output'      => 10.00,
            'cache_write' => 2.50,
            'cache_read'  => 0.20,
        ],
    ],

    /*
     | The page is public and every recommendation costs money, so a single
     | address gets this many an hour. Past it the reader still gets a book --
     | the local scoring picks one and the canned line stands in for the pitch
     | -- they just do not get a written one.
     */
    'rate_limit' => [
        'attempts' => 20,
        'per'      => 3600,
    ],

    /*
     | Filing the recommended book as one of ours
     |
     | The result screen links to our page for the book rather than to the
     | shop's, which means writing a `books` row for a title we did not have.
     | It happens inside a reader's request and the reader is anonymous, so the
     | cap is not about cost -- it is the only thing between a script walking
     | the deck and five thousand unreviewed rows on the public shelf.
     |
     | Counted off `metadata_source` rather than kept in the cache: what matters
     | is how many records were actually created today, and that question is
     | already answered by the table.
     */
    'import' => [
        'enabled'   => env('CUPIDA_IMPORT', true),
        'daily_cap' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Watching the account
    |--------------------------------------------------------------------------
    |
    | Anthropic publishes no balance, so nothing can be read: the countdown is
    | what a bookseller typed into the panel after topping up, minus what the
    | rows have cost since. Warned rather than enforced, because that figure is
    | only right while this app is the one thing spending the key -- and because
    | what actually stops a prompt is the provider refusing it, which
    | RecommendBook already falls back from.
    |
    | `warn_below` is in dollars and `throttle` is how long a warning stays quiet
    | once sent: without it a busy afternoon sends one per swipe.
    |
    */

    'credit' => [
        'warn_below' => (float)env('CUPIDA_CREDIT_WARN_BELOW', 1.00),
        'throttle'   => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Where the pool comes from
    |--------------------------------------------------------------------------
    |
    | The three JSON files under resources/data/cupida are written by
    | `php artisan cupida:scrape` off the shop's live site and committed. They
    | are regenerated by hand when the stock has moved on; nothing regenerates
    | them at deploy or in CI, and the app only ever reads them.
    |
    */

    'data_path' => resource_path('data/cupida'),

    /*
    |--------------------------------------------------------------------------
    | Demo greetings
    |--------------------------------------------------------------------------
    |
    | /la-cupida/{guest} opens the page already greeting somebody by name, so
    | the section can be shown to one person without an account being made for
    | them. The names are a whitelist and not a free URL segment on purpose:
    | the segment is written onto the shop's own public page, so anything that
    | is not on this list is a 404 rather than a word a stranger chose.
    |
    | The key is what goes in the URL; the value is what the card says.
    |
    */

    'guests' => [
        'lorena' => 'Lorena',
    ],

    /*
     | How much of a synopsis is kept.
     |
     | The shops are several hundred words each and 1,800 of them make a file
     | that has to be decoded on every request that touches the catalog. Only
     | the front of one is ever used: the shortlist matches mood keywords, which
     | are about what the book is, and the agent is shown 400 characters. The
     | back half is plot summary and press quotes.
     */
    'synopsis_limit' => 600,

    'scrape' => [
        'base_url' => env('CUPIDA_SHOP_URL', 'https://laanonimalibreria.com'),

        /*
         | The shop answers a first request with a two-line page that sets a
         | cookie in JavaScript and reloads. Nothing else on the site works
         | until that cookie comes back, and no browser header gets past it --
         | the value has to be read out of that first body. The pages are also
         | served as ISO-8859-1, so every response is converted before it is
         | parsed or every accent arrives as mojibake.
         */
        'challenge_cookie' => 'ew_hc',

        /*
         | Names the shop's own listing spells wrong, and what they are.
         |
         | Nothing in this scraper mangles a character: the pages are ISO-8859-1
         | and the conversion keeps every Spanish accent in the pool intact.
         | What the shop has lost is the letters Spanish does not use. There is
         | not one å, ø or æ in five thousand books, and where one belongs there
         | is a space -- so "Knausgård" is listed as "Knausg rd" and comes back
         | as the card "Karl Ove Knausg Rd".
         |
         | Left alone that is worse than a typo. Knausgård is filed under both
         | spellings, which splits his books across two authors, and neither
         | half is deep enough to be worth dealing -- and because
         | `CupidaShortlist::authorOf()` matches a book to a liked card by the
         | slug of this very string, saying yes to one of him scores the other's
         | books at nothing.
         |
         | So it is corrected here, on the way in, and the key is the shop's
         | spelling exactly as it appears. A re-scrape brings the broken name
         | back every time -- it is their data, not ours -- which is why this
         | is a map in the config and not an edit to books.json.
         */
        'author_aliases' => [
            'Knausg Rd, Karl Ove' => 'Knausgård, Karl Ove',
            'Knausgard, Karl Ove' => 'Knausgård, Karl Ove',
            'Bj Rk, Samuel'       => 'Bjørk, Samuel',
            'Lindstr M, Merethe'  => 'Lindstrøm, Merethe',
        ],

        'user_agent' => env(
            'CUPIDA_USER_AGENT',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
        ),

        'timeout' => 30,

        /*
         | Between requests. The scrape makes a few hundred of them against a
         | small shop's shared hosting, so it walks rather than runs.
         */
        'delay_ms' => 400,

        /*
         | How many listing pages to take from each subject. A page is 36 books.
         */
        'pages_per_subject' => 3,

        /*
         | The shop's curated shelves, taken whole. These are the books a
         | bookseller has actually chosen, so they are worth more to a
         | recommender than another page of a subject listing.
         */
        'specials' => [
            'especial/9997/recomendaciones-anonimas',
            'especial/9999/novedades',
            'especial/501/los-mas-vendidos',
            'especial/31/feminismo',
            'especial/40/ensayo',
            'especial/50/grafica',
            'especial/20/infantil',
            'especial/30/juvenil',
            'especial/1/para-regalar-te',
        ],
    ],

];
