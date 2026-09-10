<?php

use App\Actions\Cupida\RecommendBook;
use App\Ai\Agents\CupidaAgent;
use App\Models\Book;
use App\Settings\CupidaSettings;
use App\Support\Cupida\CupidaCatalog;
use App\Support\Cupida\CupidaShortlist;
use App\Support\Cupida\Recommendation;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Serializer;
use Laravel\Ai\Ai;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function(): void {
    useCupidaFixture();

    config(['ai.providers.anthropic.key' => null]);
});

it('puts what was liked at the top of the shortlist', function(): void {
    $shortlist = app(CupidaShortlist::class)->for(
        app(CupidaCatalog::class),
        likes: ['theme:DC'],
        passes: [],
    );

    expect($shortlist[0]['subjects'])->toContain('DC');
});

it('reads a liked author as the strongest thing a reader said', function(): void {
    $shortlist = app(CupidaShortlist::class)->for(
        app(CupidaCatalog::class),
        likes: ['theme:FM', 'author:guerriero-leila'],
        passes: [],
    );

    expect($shortlist[0]['author'])->toBe('Guerriero, Leila');
});

it('matches a subject through the codes filed under it', function(): void {
    /* The book is filed JBSF; the card said JB. THEMA nests by prefix, so the
       two are the same shelf. */
    $shortlist = app(CupidaShortlist::class)->for(
        app(CupidaCatalog::class),
        likes: ['theme:JB'],
        passes: [],
    );

    $eans = array_column($shortlist, 'ean');

    expect($eans)->toContain('9788410249516');
});

it('does not read a narrow card as its parent', function(): void {
    /* "Ollis" is filed JB and nothing more; the other book is filed JBSF. A
       reader who asked for Feminismos asked about the second one -- matching
       upwards as well as downwards would score both the same and make every
       narrow card a duplicate of the broad one above it. */
    $shortlist = app(CupidaShortlist::class)->for(
        app(CupidaCatalog::class),
        likes: ['theme:JBSF'],
        passes: [],
    );

    $eans = array_column($shortlist, 'ean');

    expect(array_search('9788410249516', $eans, true))
        ->toBeLessThan(array_search('9791387563127', $eans, true));
});

it('reads a mood off the synopsis, accents and all', function(): void {
    $shortlist = app(CupidaShortlist::class)->for(
        app(CupidaCatalog::class),
        likes: ['mood:heartbreak'],
        passes: [],
    );

    /* "duelo" and "pérdida" are in both, and neither card named a subject. */
    expect(array_column(array_slice($shortlist, 0, 2), 'ean'))
        ->toContain('9788419490421')
        ->toContain('9788412976137');
});

it('still hands back a book when every card was passed', function(): void {
    $shortlist = app(CupidaShortlist::class)->for(
        app(CupidaCatalog::class),
        likes: [],
        passes: ['theme:DC', 'theme:FM', 'author:guerriero-leila'],
    );

    expect($shortlist)->not->toBeEmpty();
});

it('sends a reader to our own page for a book we stock too', function(): void {
    $ours = Book::factory()->create([
        'title'  => 'La llamada',
        'isbn13' => '9788433922069',
    ]);

    $recommendation = app(RecommendBook::class)(likes: ['author:guerriero-leila'], passes: []);

    expect($recommendation->ean)->toBe('9788433922069')
        ->and($recommendation->book?->is($ours))->toBeTrue()
        ->and($recommendation->url)->toBe(route('books.show', $ours));
});

it('files a book only the shop has and sends the reader to our page for it', function(): void {
    /* This used to send the reader out to the old site, which is most readers:
       the pool is five thousand books and our catalog is a fraction of it. The
       book is filed on the way past instead, so the shop's page becomes the
       second link rather than the only one. */
    $recommendation = app(RecommendBook::class)(likes: ['theme:FM'], passes: []);

    expect($recommendation->book)->not->toBeNull()
        ->and($recommendation->book->isbn13)->toBe($recommendation->ean)
        ->and($recommendation->url)->toBe(route('books.show', $recommendation->book))
        ->and($recommendation->shopUrl)->toStartWith(config('cupida.scrape.base_url') . '/libros/')
        ->and($recommendation->written)->toBeFalse()
        ->and($recommendation->pitch)->toBe(__('cupida.result.fallback_pitch'));
});

it('sends a reader to the shop when the book could not be filed', function(): void {
    config()->set('cupida.import.enabled', false);

    $recommendation = app(RecommendBook::class)(likes: ['theme:FM'], passes: []);

    expect($recommendation->book)->toBeNull()
        ->and($recommendation->url)->toStartWith(config('cupida.scrape.base_url') . '/libros/')
        ->and($recommendation->url)->toBe($recommendation->shopUrl);
});

it('never offers a book the shop has taken off the site', function(): void {
    config()->set('cupida.data_path', base_path('tests/Fixtures/cupida/nothing-here'));

    app()->forgetInstance(CupidaCatalog::class);

    expect(app(RecommendBook::class)(likes: ['theme:DC'], passes: []))->toBeNull();
});

it('takes a liked cover as the strongest thing a reader said', function(): void {
    $shortlist = app(CupidaShortlist::class)->for(
        app(CupidaCatalog::class),
        likes: ['theme:FM', 'book:9788412976137'],
        passes: [],
    );

    expect($shortlist[0]['ean'])->toBe('9788412976137');
});

it('never hands back a book whose cover was turned down', function(): void {
    $shortlist = app(CupidaShortlist::class)->for(
        app(CupidaCatalog::class),
        likes: ['theme:DC'],
        passes: ['book:9788412976137'],
    );

    expect(array_column($shortlist, 'ean'))->not->toContain('9788412976137');
});

it('lets a liked author through only so many times', function(): void {
    config()->set('cupida.shortlist_per_author', 1);

    $shortlist = app(CupidaShortlist::class)->for(
        app(CupidaCatalog::class),
        likes: ['author:guerriero-leila'],
        passes: [],
    );

    $authors = array_column($shortlist, 'author');

    expect($authors[0])->toBe('Guerriero, Leila')
        ->and(array_count_values($authors)['Guerriero, Leila'])->toBe(1)
        /* The cap makes room; it does not shorten the list. */
        ->and(count($shortlist))->toBeGreaterThan(5);
});

it('leaves out the writers it is told to', function(): void {
    $shortlist = app(CupidaShortlist::class)->for(
        app(CupidaCatalog::class),
        likes: ['author:guerriero-leila'],
        passes: [],
        withoutAuthors: ['guerriero-leila'],
    );

    expect(array_column($shortlist, 'author'))->not->toContain('Guerriero, Leila')
        ->and($shortlist)->not->toBeEmpty();
});

it('leaves the writers out of the fallback list as well', function(): void {
    $shortlist = app(CupidaShortlist::class)->for(
        app(CupidaCatalog::class),
        likes: [],
        passes: ['theme:DC'],
        withoutAuthors: ['guerriero-leila'],
        perAuthor: 1,
    );

    expect(array_column($shortlist, 'author'))->not->toContain('Guerriero, Leila')
        ->and($shortlist)->not->toBeEmpty();
});

it('lifts the neighbors of a liked book, not only the book', function(): void {
    /* Both are Leila Guerriero; saying yes to one is saying something about
       the other. */
    $shortlist = app(CupidaShortlist::class)->for(
        app(CupidaCatalog::class),
        likes: ['book:9788433922069'],
        passes: [],
    );

    expect(array_column(array_slice($shortlist, 0, 2), 'ean'))
        ->toContain('9788433922069')
        ->toContain('9788493764395');
});

it('asks for Spanish twice, in the prompt and beside each field it writes', function(): void {
    /* Haiku has been seen opening a pitch with an English translation of the
       Spanish synopsis it was handed, while `match_line` -- whose description
       pins the shape of the line -- stayed in Spanish through the same answer.
       Saying it in the prompt alone was not enough, so both places are held
       here. */
    $agent = new CupidaAgent([]);

    expect($agent->baseInstructions())->toContain('solo en español de España');

    $fields = array_map(
        Serializer::serialize(...),
        $agent->schema(new JsonSchemaTypeFactory),
    );

    expect($fields['pitch']['description'])->toStartWith('En español.')
        ->and($fields['match_line']['description'])->toStartWith('En español.');
});

it('prefers our own synopsis to the shop\'s, which the scrape cuts at six hundred', function(): void {
    /* `cupida:scrape` stores the shop's text through `Str::limit()`, so every
       row in the pool stops at `cupida.synopsis_limit` and most stop mid-word.
       A book that is also one of ours has the whole thing. */
    $ours = 'La nuestra, entera y sin cortar a mitad de una palabra.';

    $book = Book::factory()->create([
        'isbn13'   => '9788412976137',
        'synopsis' => $ours,
    ]);

    $pool = app(CupidaCatalog::class)->book('9788412976137');

    expect(Recommendation::make($pool, 'x', null, false)->synopsis)->toBe($ours);

    $book->update(['synopsis' => null]);

    expect(Recommendation::make($pool, 'x', null, false)->synopsis)->toBe($pool['synopsis']);
});

it('drops the pool\'s half-sentence, and leaves a whole one alone', function(): void {
    /* `Str::limit()` counts characters, so the six hundredth lands mid-word:
       "sigue a un puñado de extraordin...". That did not matter while the
       synopsis only fed the scoring and is the first thing a reader sees now
       that the panel shows it. Its own "..." is what says it was cut, so a
       synopsis that arrived whole keeps its last sentence. */
    $pool = ['ean' => '9788412976137', 'slug' => 'x', 'title' => 'X'];

    $cut = Recommendation::make(
        [...$pool, 'synopsis' => 'Una primera frase entera. Y una segunda que se queda a medio decir...'],
        'x',
        null,
        false,
    );

    expect($cut->synopsis)->toBe('Una primera frase entera.');

    $whole = Recommendation::make(
        [...$pool, 'synopsis' => 'Una primera frase entera. Y una segunda que termina.'],
        'x',
        null,
        false,
    );

    expect($whole->synopsis)->toBe('Una primera frase entera. Y una segunda que termina.');

    /* No sentence break to fall back to: half of something beats none of it. */
    $unbroken = Recommendation::make(
        [...$pool, 'synopsis' => 'Una sola frase larguísima que nunca llega a terminar...'],
        'x',
        null,
        false,
    );

    expect($unbroken->synopsis)->toBe('Una sola frase larguísima que nunca llega a terminar...');
});

it('puts the space back where the shop ran two sentences together', function(): void {
    /* Their listing pastes cover quotes onto the description with no space --
       "que nunca.Un regalo para todos sus lectores" -- and a re-scrape brings
       it back every time, so the repair is here and not in books.json. Narrow
       on purpose: an abbreviation in capitals keeps its shape. */
    $pool = ['ean' => '9788412976137', 'slug' => 'x', 'title' => 'X'];

    $recommendation = Recommendation::make(
        [...$pool, 'synopsis' => 'Una Isabel más Allende que nunca.Un regalo. Vivió en EE.UU. y volvió.'],
        'x',
        null,
        false,
    );

    expect($recommendation->synopsis)
        ->toBe('Una Isabel más Allende que nunca. Un regalo. Vivió en EE.UU. y volvió.');

    /* And in our own row too. `ImportShopBook` files it from the same listing,
       so the defect follows the text rather than the catalog it came from --
       which a screenshot of a book we stock is what caught. */
    Book::factory()->create([
        'isbn13'   => '9788412976137',
        'synopsis' => 'La más premiada de la temporada en Francia.En 1919, en un bosque.',
    ]);

    expect(Recommendation::make([...$pool, 'synopsis' => 'x'], 'x', null, false)->synopsis)
        ->toBe('La más premiada de la temporada en Francia. En 1919, en un bosque.');
});

it('asks the pitch for why this one, and leaves the plot to the synopsis under it', function(): void {
    /* Folding the reason into the pitch rather than adding a fourth field: the
       page already shows the shop's synopsis below, so the librera's paragraph
       is the half a catalog cannot write -- why she is handing this one to this
       reader -- and repeating the plot would spend it on what is already there. */
    $agent = new CupidaAgent([]);

    expect($agent->baseInstructions())
        ->toContain('por qué se lo das a ella justamente')
        ->toContain('la página enseña la sinopsis de la librería');

    $fields = array_map(
        Serializer::serialize(...),
        $agent->schema(new JsonSchemaTypeFactory),
    );

    expect($fields['pitch']['description'])->toContain('no cuentes el argumento');
});

it('bars the single English word as well as the English sentence', function(): void {
    /* Both misses in the log are in the pitch and only one is a translation: a
       whole opening sentence in English, and "es confessional, rabiosa" -- an
       English adjective inside a Spanish sentence, which reads as Spanish until
       it is looked at. What is barred is the English spelling of a word Spanish
       already has, not every English word: "cruising" in a pitch about queer
       desire is the word Spanish uses. */
    $agent = new CupidaAgent([]);

    expect($agent->baseInstructions())
        ->toContain('Ni una frase entera ni una palabra suelta')
        ->toContain('como "cruising" o "thriller"');

    $fields = array_map(
        Serializer::serialize(...),
        $agent->schema(new JsonSchemaTypeFactory),
    );

    expect($fields['pitch']['description'])->toContain('Ni una frase ni un adjetivo en inglés.');
});

it('hands the model what the reader actually swiped, in the words on the cards', function(): void {
    /* Everything the reader said is scored in PHP and only the books cross to
       the prompt, so before this the model was writing "porque buscas X" about
       answers it had never been shown -- and inventing X out of whatever else
       was in the prompt. */
    config()->set('ai.providers.anthropic.key', 'test-key');

    Ai::fakeAgent(CupidaAgent::class, [[
        'ean'        => '9788412976137',
        'pitch'      => 'Se lee de una sentada.',
        'match_line' => 'Porque dijiste que sí a la poesía.',
    ]]);

    app(RecommendBook::class)(
        likes: ['theme:DC', 'author:guerriero-leila', 'mood:short'],
        passes: ['theme:FM'],
    );

    CupidaAgent::assertPrompted(function(AgentPrompt $prompt): bool {
        expect($prompt->prompt)
            ->toContain('Ha dicho que sí a: Poesía, Leila Guerriero, Algo corto, que voy justa de tiempo.')
            ->toContain('Y que no a: Fantasía.');

        return true;
    });
});

it('bars the match line from reading back the swipes, in the prompt and beside the field', function(): void {
    /* Handing the model the reader's answers stopped it inventing a taste and
       started it reciting one: every line came back as "porque dijiste que sí a
       X, a Y y a Z" -- the card labels verbatim, moods included, which are
       written in the reader's own mouth ("que me tenga en vilo") and so came
       back ungrammatical as well. The line is a send-off to a date now, and
       both places that write it say so. */
    $agent = new CupidaAgent([]);

    expect($agent->baseInstructions())
        ->toContain('No se las recites.')
        ->toContain('es una cita a ciegas');

    $fields = array_map(
        Serializer::serialize(...),
        $agent->schema(new JsonSchemaTypeFactory),
    );

    expect($fields['match_line']['description'])
        ->toContain('Nunca una lista de sus respuestas')
        ->not->toContain('empezando por "Porque"');
});

it('tells the model the shop\'s own block is the librera and never the reader', function(): void {
    /* A bookseller writes a persona in the panel -- "es queer", "es de
       izquierdas" -- and without the fence it comes back out of the prompt as
       what the reader asked for, so every match line told a stranger they were
       looking for something queer. */
    $settings = app(CupidaSettings::class);
    $settings->extra_instructions = 'La Cupida es queer.';
    $settings->save();

    $instructions = (string)new CupidaAgent([])->instructions();

    expect($instructions)
        ->toContain('La Cupida es queer.')
        ->toContain('Todo lo que sigue te describe a ti. No describe a quien está leyendo')
        ->and(new CupidaAgent([])->baseInstructions())
        ->toContain('Nunca le atribuyas un tema, un gusto ni una identidad que no haya elegido.');
});

it('presents the book as a match and not as a review', function(): void {
    /* Thirteen sessions in the log and not one pitch sounded like the page's
       promise: ten opened with the author's name or the title, both of which
       are printed right above the paragraph, and every one read like a ficha.
       The prompt now names the register -- a casamentera sure of the pair --
       and bars the author-first opening in both places that write the pitch. */
    $agent = new CupidaAgent([]);

    expect($agent->baseInstructions())
        ->toContain('casamentera')
        ->toContain('No empieces por el nombre de quien lo escribió ni por el título')
        ->toContain('Nada de "creo que te gustará"');

    $fields = array_map(
        Serializer::serialize(...),
        $agent->schema(new JsonSchemaTypeFactory),
    );

    expect($fields['pitch']['description'])
        ->toContain('con quien va a saltar la chispa')
        ->toContain('Sin empezar por el título ni por quien lo escribió')
        ->toContain('Sin "porque dijiste" ni "porque buscas".');
});

it('never lets a pass become the argument', function(): void {
    /* "sin que sea oscuro", "que no te robará semanas", "que no te deje con
       esperanza": four pitches in the log sold the book by a card the reader
       had turned down. A pass discards books; it is not a reason. */
    expect(new CupidaAgent([])->baseInstructions())
        ->toContain('Lo que ha dicho que no sirve para descartar libros, nunca como argumento.')
        /* The list form was barred and the model quoted one card back on its
           own ("porque dijiste que querías reírte en el metro"), so the single
           quote is named too. */
        ->toContain('"porque dijiste", "porque pediste", "porque buscas" no aparecen nunca');
});

it('holds what it says about the book to the synopsis', function(): void {
    /* Realismo mágico on a thriller, "dibujo europeo" on an American, fantasía
       romántica on an art-history book: the model reached for a liked card to
       explain the book instead of for the synopsis it was handed. */
    expect(new CupidaAgent([])->baseInstructions())
        ->toContain('Una carta a la que ha dicho que sí no es una prueba')
        ->toContain('quién lo ilustra, qué premio tiene ni de qué edición es');
});

it('sends her off to the date, not to a moral', function(): void {
    /* The one send-off written under the previous prompt was "Que encuentres
       en la penumbra tu propia verdad": life advice, nothing about the night
       with the book. Both places that write the line say what it is now. */
    $agent = new CupidaAgent([]);

    expect($agent->baseInstructions())
        ->toContain('Es una despedida en la puerta,')
        ->toContain('no un consejo de vida')
        ->toContain('palabra que le prometiste.');

    $fields = array_map(
        Serializer::serialize(...),
        $agent->schema(new JsonSchemaTypeFactory),
    );

    expect($fields['match_line']['description'])
        ->toContain('La despedida en la puerta antes de la cita')
        ->toContain('Sobre la lectura, no sobre su vida.')
        ->toContain('(cita, flechazo, crush, match)')
        ->toContain('ni un consejo de vida');
});

it('tells the model the word the opening card promised', function(): void {
    /* The first screen promises "tu próxima cita" or "tu próximo flechazo" by
       seed, and until now the model never heard which, so nothing it wrote
       could keep the promise. It is named beside the answers, where the
       session-specific facts go; the base prompt only says the word exists. */
    config()->set('ai.providers.anthropic.key', 'test-key');

    Ai::fakeAgent(CupidaAgent::class, [[
        'ean'        => '9788412976137',
        'pitch'      => 'Se lee de una sentada.',
        'match_line' => 'Que te dure toda la noche.',
    ]]);

    app(RecommendBook::class)(
        likes: ['theme:DC'],
        passes: [],
        promise: 'tu próximo flechazo',
    );

    CupidaAgent::assertPrompted(function(AgentPrompt $prompt): bool {
        expect($prompt->prompt)
            ->toStartWith('Le prometiste tu próximo flechazo.')
            ->toContain('Ha dicho que sí a: Poesía.');

        return true;
    });
});

it('says nothing about a promise when none was made', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');

    Ai::fakeAgent(CupidaAgent::class, [[
        'ean'        => '9788412976137',
        'pitch'      => 'Se lee de una sentada.',
        'match_line' => 'Que te dure toda la noche.',
    ]]);

    app(RecommendBook::class)(likes: ['theme:DC'], passes: []);

    CupidaAgent::assertPrompted(function(AgentPrompt $prompt): bool {
        expect($prompt->prompt)
            ->not->toContain('Le prometiste')
            ->toStartWith('Esto es lo que ha respondido');

        return true;
    });
});
