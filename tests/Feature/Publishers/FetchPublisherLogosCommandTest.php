<?php

use App\Models\Publisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function(): void {
    Storage::fake('public');
    Sleep::fake();
    Http::preventStrayRequests();
    $this->freezeTime();
});

/** A publisher somebody already uploaded a logotype for. */
function publisherWithLogo(array $attributes = []): Publisher
{
    $publisher = Publisher::factory()->create(['website' => null, ...$attributes]);
    $publisher->addMediaFromString(fakeLogo())->usingFileName('propio.png')->toMediaCollection(Publisher::LOGO_COLLECTION);

    return $publisher;
}

it('asks only about publishers with no logotype that nobody has asked about', function(): void {
    fakePublisherWikidata([]);
    $fresh = Publisher::factory()->create(['website' => null]);
    $asked = Publisher::factory()->create(['website' => null]);
    $asked->forceFill(['logo_checked_at' => now()->subWeek()])->saveQuietly();
    $withLogo = publisherWithLogo();

    $this->artisan('publishers:logos')->assertSuccessful();

    expect($fresh->refresh()->logo_checked_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($asked->refresh()->logo_checked_at?->toDateTimeString())->toBe(now()->subWeek()->toDateTimeString())
        ->and($withLogo->refresh()->logo_checked_at)->toBeNull();
});

it('asks again about earlier misses with --retry-misses', function(): void {
    fakePublisherWikidata([]);
    $asked = Publisher::factory()->create(['website' => null]);
    $asked->forceFill(['logo_checked_at' => now()->subWeek()])->saveQuietly();
    $withLogo = publisherWithLogo();

    $this->artisan('publishers:logos --retry-misses')->assertSuccessful();

    expect($asked->refresh()->logo_checked_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($withLogo->refresh()->logo_checked_at)->toBeNull();
});

it('replaces a logotype the publisher already has with --force', function(): void {
    fakePublisherWikidata(['Norma Editorial' => 'search-norma']);
    Http::fake(['thumb.wikimedia.org/*' => Http::response(fakeLogo())]);
    $publisher = publisherWithLogo(['name' => 'Norma Editorial']);

    $this->artisan('publishers:logos --force')->assertSuccessful();

    expect($publisher->refresh()->getFirstMedia(Publisher::LOGO_COLLECTION)->file_name)->toBe('norma-editorial-logo.png');
});

it('narrows the run with --only and --limit', function(): void {
    fakePublisherWikidata([]);
    $alfaguara = Publisher::factory()->create(['name' => 'Alfaguara', 'website' => null]);
    $blackie = Publisher::factory()->create(['name' => 'Blackie Books', 'website' => null]);
    $capitan = Publisher::factory()->create(['name' => 'Capitán Swing', 'website' => null]);

    $this->artisan('publishers:logos --only=blackie-books,capitan-swing --limit=1')->assertSuccessful();

    expect($alfaguara->refresh()->logo_checked_at)->toBeNull()
        ->and($blackie->refresh()->logo_checked_at)->not->toBeNull()
        ->and($capitan->refresh()->logo_checked_at)->toBeNull();
});

it('refuses --qid unless it names exactly one publisher', function(): void {
    Http::fake();
    Publisher::factory()->create(['website' => null]);

    $this->artisan('publishers:logos --qid=Q2309266')->assertFailed();

    Http::assertNothingSent();
});
