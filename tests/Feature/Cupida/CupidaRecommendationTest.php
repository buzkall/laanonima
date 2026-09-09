<?php

use App\Actions\Cupida\RecommendBook;
use App\Ai\Agents\CupidaAgent;
use App\Models\Book;
use App\Support\Cupida\CupidaCatalog;
use App\Support\Cupida\CupidaShortlist;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Serializer;

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

it('sends a reader to the shop for a book only they have', function(): void {
    $recommendation = app(RecommendBook::class)(likes: ['theme:FM'], passes: []);

    expect($recommendation->book)->toBeNull()
        ->and($recommendation->url)->toStartWith(config('cupida.scrape.base_url') . '/libros/')
        ->and($recommendation->written)->toBeFalse()
        ->and($recommendation->pitch)->toBe(__('cupida.result.fallback_pitch'));
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
       pins how the line starts -- stayed in Spanish through the same answer.
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
