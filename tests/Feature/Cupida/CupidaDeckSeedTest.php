<?php

use App\Support\Cupida\CupidaCard;
use App\Support\Cupida\CupidaCatalog;
use App\Support\Cupida\CupidaDeck;

beforeEach(function(): void {
    useCupidaFixture();
});

it('deals the same three rounds from the same seed', function(): void {
    $answers = fn(CupidaDeck $deck): array => collect($deck->rounds)
        ->flatten()
        ->map(fn($card): string => $card->answer())
        ->all();

    $catalog = app(CupidaCatalog::class);

    expect($answers(CupidaDeck::for($catalog, 1234)))
        ->toBe($answers(CupidaDeck::for($catalog, 1234)))
        ->not->toBe($answers(CupidaDeck::for($catalog, 5678)));
});

it('asks its three questions in order', function(): void {
    $deck = CupidaDeck::for(app(CupidaCatalog::class), 1234);

    expect($deck->rounds())->toBe(3)
        ->and(array_map(fn(array $round): string => $round[0]->kind, $deck->rounds))
        ->toBe(['theme', 'author', 'mood']);
});

it('never deals the same card twice in a session', function(): void {
    $answers = collect(CupidaDeck::for(app(CupidaCatalog::class), 99)->rounds)
        ->flatten()
        ->map(fn($card): string => $card->answer());

    expect($answers)->toHaveCount($answers->unique()->count());
});

it('leaves out a subject the shop has nothing on', function(): void {
    /* WB is on the deck list and has no stock; a card promising "Cocina" that
       shortlists nothing is worse than one card fewer. F is not on the list at
       all -- "Ficción y temas afines" is the standard's heading, not a
       question. */
    $codes = collect(app(CupidaCatalog::class)->deckThemes())->pluck('code');

    expect($codes)->not->toContain('WB')
        ->and($codes)->not->toContain('F')
        ->and($codes)->toContain('FM');
});

it('deals a subject that is in the config and not in themes.json', function(): void {
    /* The whole point of reading the config rather than the scraped tree: FB
       has books in the pool and no row in themes.json, because the scrape that
       wrote the fixture never walked it. A subject is added to the deck by
       editing the config, with no scrape run. */
    $themes = app(CupidaCatalog::class);

    expect(collect($themes->themes())->pluck('code'))->not->toContain('FB')
        ->and(collect($themes->deckThemes())->pluck('code'))->toContain('FB');
});

it('counts the pool behind a card, not the shop total', function(): void {
    /* themes.json records what the shop stocks under DC -- 619 books. The
       shortlist can only ever offer what the pool holds, which is two, so that
       is what the card says. */
    $card = collect(app(CupidaCatalog::class)->deckThemes())->firstWhere('code', 'DC');

    expect(collect(app(CupidaCatalog::class)->themes())->firstWhere('code', 'DC')['books'])->toBe(619)
        ->and($card['books'])->toBe(2);
});

it('drops a subject the pool is too thin on', function(): void {
    config()->set('cupida.deck.min_books', 2);

    app()->forgetInstance(CupidaCatalog::class);

    $codes = collect(app(CupidaCatalog::class)->deckThemes())->pluck('code');

    /* One book under FB, two under DC. */
    expect($codes)->not->toContain('FB')
        ->and($codes)->toContain('DC');
});

it('names a subject that has no row in themes.json', function(): void {
    $catalog = app(CupidaCatalog::class);

    expect($catalog->subjectLabel('FFL'))->toBe('Novela negra')
        ->and($catalog->subjectLabel('ZZZ'))->toBe('ZZZ');
});

it('never paints two neighboring cards the same color', function(): void {
    foreach (CupidaDeck::for(app(CupidaCatalog::class), 4242)->rounds as $round) {
        $colors = array_map(fn(CupidaCard $card): string => $card->palette->background, $round);

        foreach (array_slice($colors, 1) as $index => $color) {
            expect($color)->not->toBe($colors[$index]);
        }
    }
});
