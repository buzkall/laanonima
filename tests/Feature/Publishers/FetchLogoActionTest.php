<?php

use App\Filament\Resources\Publishers\Pages\ListPublishers;
use App\Models\Publisher;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    Storage::fake('public');
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->admin()->create());
});

it('files the logotype it finds for the row', function(): void {
    fakePublisherWikidata(['Norma Editorial' => 'search-norma']);
    Http::fake(['thumb.wikimedia.org/*' => Http::response(fakeLogo())]);
    $publisher = Publisher::factory()->create(['name' => 'Norma Editorial', 'website' => null]);

    livewire(ListPublishers::class)
        ->callAction(TestAction::make('fetchLogo')->table($publisher))
        ->assertNotified(__('publishers.logo_fetch.done_title'));

    expect($publisher->refresh()->hasMedia(Publisher::LOGO_COLLECTION))->toBeTrue();
});

it('warns when nothing usable is found', function(): void {
    fakePublisherWikidata([]);
    $publisher = Publisher::factory()->create(['website' => null]);

    livewire(ListPublishers::class)
        ->callAction(TestAction::make('fetchLogo')->table($publisher))
        ->assertNotified(__('publishers.logo_fetch.missing_title'));

    expect($publisher->refresh()->hasMedia(Publisher::LOGO_COLLECTION))->toBeFalse();
});

it('asks before looking again for a publisher that has a logotype', function(): void {
    Http::fake();
    $publisher = Publisher::factory()->create();
    $publisher->addMediaFromString(fakeLogo())->usingFileName('propio.png')->toMediaCollection(Publisher::LOGO_COLLECTION);

    livewire(ListPublishers::class)
        ->mountAction(TestAction::make('fetchLogo')->table($publisher))
        ->assertActionMounted(TestAction::make('fetchLogo')->table($publisher));

    Http::assertNothingSent();
});

it('stays available while the demo is open', function(): void {
    config()->set('site.demo_mode', true);
    $publisher = Publisher::factory()->create();

    livewire(ListPublishers::class)->assertActionVisible(TestAction::make('fetchLogo')->table($publisher));
});
