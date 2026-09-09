<?php

use App\Support\Portraits\WikidataPortraitSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wikidata answers two different endpoints from the same api.php, told apart by
 * the action parameter, so the search fake has to be keyed more narrowly than
 * the entities one and registered first.
 */
function fakeWikidata(string $name, ?string $imageinfo = 'portraits/imageinfo-sastre'): void
{
    Http::fake([
        'www.wikidata.org/w/api.php?action=wbsearchentities*' => Http::response(apiFixture("portraits/search-{$name}")),
        'www.wikidata.org/w/api.php*'                         => Http::response(apiFixture("portraits/entities-{$name}")),
        'commons.wikimedia.org/w/api.php*'                    => $imageinfo === null
            ? Http::response('', 500)
            : Http::response(apiFixture($imageinfo)),
    ]);
}

it('resolves a writer to a portrait with its license', function(): void {
    fakeWikidata('sastre');

    $match = app(WikidataPortraitSource::class)->find('Elvira Sastre');

    expect($match)->not->toBeNull()
        ->and($match->qid)->toBe('Q27670801')
        ->and($match->label)->toBe('Elvira Sastre')
        ->and($match->description)->toBe('poeta y traductora española')
        ->and($match->hasImage())->toBeTrue()
        ->and($match->file)->toBe('File:Elvira Sastre.jpg')
        ->and($match->license)->toBe('CC BY-SA 4.0')
        ->and($match->attributionRequired)->toBeTrue()
        ->and($match->sourceUrl)->toBe('https://commons.wikimedia.org/wiki/File:Elvira_Sastre.jpg');
});

it('stores the photographer as plain text, not as the anchor Commons returns', function(): void {
    fakeWikidata('sastre');

    /* extmetadata.Artist arrives as `<a href="...">Florenciac</a>`, and the
       credits block on /la-cupida prints it. */
    expect(app(WikidataPortraitSource::class)->find('Elvira Sastre')->artist)->toBe('Florenciac');
});

it('strips the tracking parameters Commons hangs off the thumbnail', function(): void {
    fakeWikidata('sastre');

    /* The URL is committed and re-fetched on every deploy for years, so it
       should name the file and nothing else. */
    expect(app(WikidataPortraitSource::class)->find('Elvira Sastre')->imageUrl)
        ->toBe('https://thumb.wikimedia.org/wikipedia/commons/thumb/9/93/Elvira_Sastre.jpg/960px-Elvira_Sastre.jpg');
});

it('prefers the writer with no photo over the musician who has one', function(): void {
    /* The real reason the occupation guard exists. Searching "Mary Oliver"
       returns the poet (Q454836, no P18) ahead of a Dutch jazz singer
       (Q1906359, who does have a photo). Anything that reaches for the first
       candidate *with an image* puts a stranger's face on the card. */
    fakeWikidata('oliver');

    $match = app(WikidataPortraitSource::class)->find('Mary Oliver');

    expect($match)->not->toBeNull()
        ->and($match->qid)->toBe('Q454836')
        ->and($match->qid)->not->toBe('Q1906359')
        ->and($match->hasImage())->toBeFalse();
});

it('identifies a writer nobody has photographed rather than giving up on them', function(): void {
    fakeWikidata('oliver');

    /* A match with no file is a different verdict from no match at all: one is
       "no_image" and is worth asking about again, the other is "no_match". */
    $match = app(WikidataPortraitSource::class)->find('Mary Oliver');

    expect($match->file)->toBeNull()
        ->and($match->occupations)->toContain('Q49757');
});

it('skips a candidate that is not a human', function(): void {
    fakeWikidata('oliver');

    /* Q1272454 is a disambiguation page and sits in the same search results. */
    expect(app(WikidataPortraitSource::class)->find('Mary Oliver')->qid)->not->toBe('Q1272454');
});

it('returns null and logs when the search finds nothing', function(): void {
    Log::spy();
    Http::fake(['www.wikidata.org/*' => Http::response(['search' => []])]);

    expect(app(WikidataPortraitSource::class)->find('Nadie En Absoluto'))->toBeNull();
});

it('returns null rather than throwing when Wikidata answers with an error', function(): void {
    Log::spy();
    Http::fake(['www.wikidata.org/*' => Http::response('', 500)]);

    expect(app(WikidataPortraitSource::class)->find('Elvira Sastre'))->toBeNull();

    Log::shouldHaveReceived('warning')->atLeast()->once();
});

it('returns null rather than throwing on a malformed body', function(): void {
    Log::spy();
    Http::fake(['www.wikidata.org/*' => Http::response('not json at all')]);

    expect(app(WikidataPortraitSource::class)->find('Elvira Sastre'))->toBeNull();
});

it('keeps the identification when Commons will not answer for the file', function(): void {
    Log::spy();
    fakeWikidata('sastre', imageinfo: null);

    /* We know who she is; we just could not get the photo this time. Recording
       that as "no match" would stop the next run ever asking again. */
    $match = app(WikidataPortraitSource::class)->find('Elvira Sastre');

    expect($match)->not->toBeNull()
        ->and($match->qid)->toBe('Q27670801')
        ->and($match->hasImage())->toBeFalse();
});

it('takes a hand-pinned Commons file without asking Wikidata at all', function(): void {
    fakeWikidata('sastre');

    /* The escape hatch for the one thing no guard can catch: the right person
       whose P18 is a statue, a book cover or a group shot. */
    $match = app(WikidataPortraitSource::class)->find('Cualquiera', file: 'File:Elvira Sastre.jpg');

    expect($match->hasImage())->toBeTrue()
        ->and($match->qid)->toBeNull();

    Http::assertNotSent(fn($request): bool => str_contains($request->url(), 'wbsearchentities'));
});
