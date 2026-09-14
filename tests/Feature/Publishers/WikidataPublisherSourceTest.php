<?php

use App\Support\PublisherLogos\WikidataPublisherSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function(): void {
    Http::preventStrayRequests();
});

it('identifies a publisher with its logotype, license and website', function(): void {
    fakePublisherWikidata(['Norma Editorial' => 'search-norma']);

    $publisher = app(WikidataPublisherSource::class)->find('Norma Editorial');

    expect($publisher)->not->toBeNull()
        ->and($publisher->qid)->toBe('Q2309266')
        ->and($publisher->website)->toBe('http://www.normaeditorial.com')
        ->and($publisher->hasLogo())->toBeTrue()
        ->and($publisher->file)->toBe('File:Norma Editorial.svg')
        ->and($publisher->license)->toBe('Public domain')
        ->and($publisher->artist)->toBe('Adruki');
});

it('asks for the rasterised thumbnail without its tracking parameters', function(): void {
    fakePublisherWikidata(['Norma Editorial' => 'search-norma']);

    /* Nothing here decodes an SVG; Commons hands over a PNG of one. */
    expect(app(WikidataPublisherSource::class)->find('Norma Editorial')->imageUrl)
        ->toBe('https://thumb.wikimedia.org/wikipedia/commons/thumb/8/8c/Norma_Editorial.svg/960px-Norma_Editorial.svg.png');
});

it('searches again without the legal form the catalog files the name with', function(): void {
    /* The real reason for the retry: Wikidata finds nothing at all for the
       name the shop uses. */
    fakePublisherWikidata(['Norma Editorial' => 'search-norma']);

    $publisher = app(WikidataPublisherSource::class)->find('NORMA EDITORIAL, S.A.');

    expect($publisher?->qid)->toBe('Q2309266');

    Http::assertSent(fn(Request $request): bool => ($request->data()['search'] ?? null) === 'Norma Editorial, S.A.');
    Http::assertSent(fn(Request $request): bool => ($request->data()['search'] ?? null) === 'Norma Editorial');
});

it('skips the constellation, the missile and the rocket to find the imprint', function(): void {
    fakePublisherWikidata(['Taurus' => 'search-taurus'], 'entities-taurus');

    $publisher = app(WikidataPublisherSource::class)->find('Taurus');

    expect($publisher?->qid)->toBe('Q6139319')
        ->and($publisher->hasLogo())->toBeFalse()
        ->and($publisher->website)->toBe('https://www.penguinlibros.com/es/11335-taurus');
});

it('rejects an item whose name only begins with the one searched', function(): void {
    /* The search is by prefix: "Sala" brings back everything called Salamandra,
       the publisher among them. */
    fakePublisherWikidata(['Sala' => 'search-salamandra'], 'entities-salamandra');

    expect(app(WikidataPublisherSource::class)->find('Sala'))->toBeNull();

    Http::assertNotSent(fn(Request $request): bool => $request['action'] === 'wbgetentities');
});

it('uses a pinned item without searching', function(): void {
    fakePublisherWikidata([]);

    expect(app(WikidataPublisherSource::class)->find('Cualquier nombre', 'Q2309266')?->qid)->toBe('Q2309266');

    Http::assertNotSent(fn(Request $request): bool => $request['action'] === 'wbsearchentities');
});

it('keeps the identification when Commons fails', function(): void {
    fakePublisherWikidata(['Norma Editorial' => 'search-norma'], commons: false);

    $publisher = app(WikidataPublisherSource::class)->find('Norma Editorial');

    expect($publisher?->qid)->toBe('Q2309266')
        ->and($publisher->hasLogo())->toBeFalse()
        ->and($publisher->website)->toBe('http://www.normaeditorial.com');
});

it('answers null and logs when Wikidata fails', function(): void {
    Log::spy();
    Http::fake(['www.wikidata.org/*' => Http::response('', 500)]);

    expect(app(WikidataPublisherSource::class)->find('Norma Editorial'))->toBeNull();

    Log::shouldHaveReceived('warning')->atLeast()->once();
});

it('tries the name bare of its legal form and then of a generic word', function(string $name, array $variants): void {
    expect(app(WikidataPublisherSource::class)->variants($name))->toBe($variants);
})->with([
    'legal form and generic word' => ['Ediciones La Cúpula, S.L.', ['Ediciones La Cúpula, S.L.', 'Ediciones La Cúpula', 'La Cúpula']],
    'parenthetical'               => ['Continta Me Tienes (Errementari S.L.)', ['Continta Me Tienes (Errementari S.L.)', 'Continta Me Tienes']],
    'trailing phrase'             => ['Altamarea Edición de Libros SL', ['Altamarea Edición de Libros SL', 'Altamarea Edición de Libros', 'Altamarea']],
    'nothing to strip'            => ['Anagrama', ['Anagrama']],
]);
