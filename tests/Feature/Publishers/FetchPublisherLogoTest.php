<?php

use App\Actions\Publishers\FetchPublisherLogo;
use App\Enums\LogoOrigin;
use App\Enums\PublisherLogoOutcome;
use App\Models\Publisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function(): void {
    Storage::fake('public');
    Http::preventStrayRequests();
});

it('files the wikidata logotype with where it came from', function(): void {
    fakePublisherWikidata(['Norma Editorial' => 'search-norma']);
    Http::fake(['thumb.wikimedia.org/*' => Http::response(fakeLogo())]);
    $publisher = Publisher::factory()->create(['name' => 'Norma Editorial', 'website' => null]);

    $result = app(FetchPublisherLogo::class)($publisher);

    $logo = $publisher->refresh()->getFirstMedia(Publisher::LOGO_COLLECTION);

    expect($result->outcome)->toBe(PublisherLogoOutcome::Attached)
        ->and($result->origin)->toBe(LogoOrigin::Wikidata)
        ->and($logo->file_name)->toBe('norma-editorial-logo.png')
        ->and($logo->getCustomProperty('source'))->toBe('wikidata')
        ->and($logo->getCustomProperty('credit.qid'))->toBe('Q2309266')
        ->and($logo->getCustomProperty('credit.license'))->toBe('Public domain');
});

it('fills in a website the publisher did not have', function(): void {
    fakePublisherWikidata(['Norma Editorial' => 'search-norma']);
    Http::fake(['thumb.wikimedia.org/*' => Http::response(fakeLogo())]);
    $publisher = Publisher::factory()->create(['name' => 'Norma Editorial', 'website' => null]);

    $result = app(FetchPublisherLogo::class)($publisher);

    expect($result->websiteFilled)->toBeTrue()
        ->and($publisher->refresh()->website)->toBe('http://www.normaeditorial.com');
});

it('never overwrites a website somebody typed', function(): void {
    fakePublisherWikidata(['Norma Editorial' => 'search-norma']);
    Http::fake(['thumb.wikimedia.org/*' => Http::response(fakeLogo())]);
    $publisher = Publisher::factory()->create(['name' => 'Norma Editorial', 'website' => 'https://normaeditorial.com/tienda']);

    $result = app(FetchPublisherLogo::class)($publisher);

    expect($result->websiteFilled)->toBeFalse()
        ->and($publisher->refresh()->website)->toBe('https://normaeditorial.com/tienda');
});

it('falls back to the icon its website declares', function(): void {
    fakePublisherWikidata([]);
    fakeHosts(['blackiebooks.org' => ['93.184.216.34']]);
    Http::fake([
        'https://blackiebooks.org/wp-content/*' => Http::response(fakeLogo(180, 180)),
        'https://blackiebooks.org/'             => Http::response(
            file_get_contents(fixture('publisher-logos/home-blackie.html')),
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);
    $publisher = Publisher::factory()->create(['name' => 'Blackie Books', 'website' => 'https://blackiebooks.org/']);

    $result = app(FetchPublisherLogo::class)($publisher);

    expect($result->origin)->toBe(LogoOrigin::Website)
        ->and($publisher->refresh()->getFirstMedia(Publisher::LOGO_COLLECTION)->getCustomProperty('credit.source_url'))
        ->toBe('https://blackiebooks.org/wp-content/uploads/2024/03/cropped-favicon-2-180x180.png');
});

it('fills in the website of an imprint but takes no icon from its publishing group', function(): void {
    fakePublisherWikidata(['Taurus' => 'search-taurus'], 'entities-taurus');
    $publisher = Publisher::factory()->create(['name' => 'Taurus', 'website' => null]);

    $result = app(FetchPublisherLogo::class)($publisher);

    expect($result->outcome)->toBe(PublisherLogoOutcome::NotFound)
        ->and($result->websiteFilled)->toBeTrue()
        ->and($publisher->refresh()->website)->toBe('https://www.penguinlibros.com/es/11335-taurus');
});

it('asks nothing about a publisher that already has a logotype', function(): void {
    Http::fake(['www.wikidata.org/*' => Http::response([], 500)]);
    $publisher = Publisher::factory()->create();
    $publisher->addMediaFromString(fakeLogo())->usingFileName('propio.png')->toMediaCollection(Publisher::LOGO_COLLECTION);

    $result = app(FetchPublisherLogo::class)($publisher);

    expect($result->outcome)->toBe(PublisherLogoOutcome::Kept)
        ->and($publisher->refresh()->logo_checked_at)->toBeNull();

    Http::assertNothingSent();
});

it('keeps the logotype it had when a retry finds nothing', function(): void {
    fakePublisherWikidata([]);
    $publisher = Publisher::factory()->create(['website' => null]);
    $publisher->addMediaFromString(fakeLogo())->usingFileName('propio.png')->toMediaCollection(Publisher::LOGO_COLLECTION);

    $result = app(FetchPublisherLogo::class)($publisher, replace: true);

    expect($result->outcome)->toBe(PublisherLogoOutcome::NotFound)
        ->and($publisher->refresh()->getFirstMedia(Publisher::LOGO_COLLECTION)->file_name)->toBe('propio.png');
});

it('stamps a miss so the next run does not ask again', function(): void {
    $this->freezeTime();
    fakePublisherWikidata([]);
    $publisher = Publisher::factory()->create(['website' => null]);

    app(FetchPublisherLogo::class)($publisher);

    expect($publisher->refresh()->logo_checked_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('keeps the transparency in the thumbnail the pages show', function(): void {
    /* Media library encodes conversions as JPEG unless told otherwise, and a
       transparent logotype came out on a black box. */
    fakePublisherWikidata(['Norma Editorial' => 'search-norma']);
    Http::fake(['thumb.wikimedia.org/*' => Http::response(fakeLogo(400, 200))]);
    $publisher = Publisher::factory()->create(['name' => 'Norma Editorial']);

    app(FetchPublisherLogo::class)($publisher);

    $logo = $publisher->refresh()->getFirstMedia(Publisher::LOGO_COLLECTION);
    $thumb = Storage::disk('public')->get($logo->getPathRelativeToRoot('thumb'));

    expect(getimagesizefromstring((string)$thumb)[2])->toBe(IMAGETYPE_PNG)
        ->and((imagecolorat(imagecreatefromstring((string)$thumb), 0, 0) >> 24) & 0x7F)->toBe(127);
});
