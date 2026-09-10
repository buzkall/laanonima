<?php

use App\Support\Cupida\CupidaCatalog;
use App\Support\Cupida\CupidaDeck;
use Illuminate\Support\Facades\File;

beforeEach(function(): void {
    useCupidaFixture();
});

/**
 * Every author the deck can reach, across enough seeds to have seen them all.
 *
 * The round is six of the pool, so one deck says nothing about who is in it;
 * the question these tests ask is always about the pool.
 *
 * @return array<int, string>
 */
function authorsDealt(int $seeds = 60): array
{
    $catalog = app(CupidaCatalog::class);
    $slugs = [];

    foreach (range(1, $seeds) as $seed) {
        foreach (CupidaDeck::for($catalog, $seed)->round(1) as $card) {
            $slugs[$card->key] = true;
        }
    }

    return array_keys($slugs);
}

it('deals every writer above the floor and none below it', function(): void {
    /* The fixture is eleven authors, one of whom has two books. The floor is
       lowered to one for the rest of the suite, so this is the only test that
       asks it the question the real pool asks: a writer with a single title is
       a card nobody recognizes, and four names in five in the real pool are
       exactly that. */
    config()->set('cupida.deck.author_min_books', 2);

    expect(authorsDealt())->toBe(['guerriero-leila']);
});

it('draws from the whole pool rather than the top of the ranking', function(): void {
    /* The floor replaced a rank cut, and the rank cut is what made the tail of
       the pool a fixed block: it landed inside a tie the scrape breaks
       alphabetically, so the writers on the far side of the alphabet could
       never be dealt at all. Kingfisher sorts last in the fixture and is
       dealt. */
    expect(authorsDealt())
        ->toContain('kingfisher-t')
        ->toContain('guerriero-leila')
        ->toHaveCount(10);
});

it('never deals a name that says it is not one writer', function(): void {
    /* The reviewed `no_person` row (ito-kaoru, covered in CupidaPortraitCardTest)
       only exists once somebody has sat down with `cupida:portraits:resolve`,
       which is run by hand and always lags a scrape. The shop-ism has to be
       caught by its spelling too, or "Vv.Aa.12" is a card for the whole of that
       gap. The fixture has no collective the patterns match, so Guerriero
       stands in for one. */
    config()->set('cupida.portraits.collective_patterns', ['leila*']);

    expect(authorsDealt())->not->toContain('guerriero-leila');
});

it('counts a writer by their titles and not by the copies on the shelf', function(): void {
    /* Two editions of one novel are one book. Counting rows put a writer with
       two novels in four bindings above one with three of their own, and the
       floor has to mean something. The subtitle is the best-stocked title, so
       a card opens a series at the volume the shop actually has a stack of
       rather than at whichever row the scrape met first. */
    $directory = base_path('tests/.tmp/authors-' . getmypid());

    File::ensureDirectoryExists($directory);
    File::put("{$directory}/books.json", json_encode([
        ['ean' => '1', 'title' => 'Dos veces', 'author' => 'Vera, Julia'],
        ['ean' => '2', 'title' => 'Dos veces', 'author' => 'Vera, Julia'],
        ['ean' => '3', 'title' => 'Una vez', 'author' => 'Vera, Julia'],
        ['ean' => '4', 'title' => 'Alfabéticamente antes', 'author' => 'Vera, Julia'],
        ['ean' => '5', 'title' => 'Su único libro', 'author' => 'Ruiz, Marta'],
    ]));

    config()->set('cupida.data_path', $directory);
    app()->forgetInstance(CupidaCatalog::class);

    $this->artisan('cupida:scrape --rebuild')->assertSuccessful();

    app()->forgetInstance(CupidaCatalog::class);
    $authors = collect(app(CupidaCatalog::class)->authors())->keyBy('slug');

    expect($authors['vera-julia']['books'])->toBe(3)
        ->and($authors['vera-julia']['titles'])
        ->toBe(['Dos veces', 'Alfabéticamente antes', 'Una vez'])
        ->and($authors['ruiz-marta']['books'])->toBe(1);
});

it('files a writer the shop spells two ways under one corrected name', function(): void {
    /* The shop's listing has no å, ø or æ in it and leaves a space where one
       belongs, so Knausgård is filed as both "Knausg rd" and "Knausgard" --
       two authors, neither deep enough to deal, and a liked card for one that
       scores the other's books at nothing. The correction has to reach
       books.json and not only authors.json for that last part to be true. */
    $directory = base_path('tests/.tmp/authors-' . getmypid());

    File::ensureDirectoryExists($directory);
    File::put("{$directory}/books.json", json_encode([
        ['ean' => '1', 'title' => 'Bailando en la oscuridad', 'author' => 'Knausg Rd, Karl Ove'],
        ['ean' => '2', 'title' => 'La estrella de la mañana', 'author' => 'Knausgard, Karl Ove'],
    ], JSON_UNESCAPED_UNICODE));

    config()->set('cupida.data_path', $directory);
    config()->set('cupida.scrape.author_aliases', [
        'Knausg Rd, Karl Ove' => 'Knausgård, Karl Ove',
        'Knausgard, Karl Ove' => 'Knausgård, Karl Ove',
    ]);
    app()->forgetInstance(CupidaCatalog::class);

    $this->artisan('cupida:scrape --rebuild')->assertSuccessful();

    app()->forgetInstance(CupidaCatalog::class);
    $catalog = app(CupidaCatalog::class);

    expect($catalog->authors())->toHaveCount(1)
        ->and($catalog->authors()[0]['name'])->toBe('Karl Ove Knausgård')
        ->and($catalog->authors()[0]['slug'])->toBe('knausgard-karl-ove')
        ->and($catalog->authors()[0]['books'])->toBe(2)
        ->and(array_column($catalog->books(), 'author'))
        ->each->toBe('Knausgård, Karl Ove');
});

afterEach(function(): void {
    File::deleteDirectory(base_path('tests/.tmp/authors-' . getmypid()));
});
