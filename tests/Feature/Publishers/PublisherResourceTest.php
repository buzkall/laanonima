<?php

use App\Filament\Resources\Publishers\Pages\CreatePublisher;
use App\Filament\Resources\Publishers\Pages\EditPublisher;
use App\Filament\Resources\Publishers\Pages\ListPublishers;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
});

it('lists publishers', function(): void {
    $publishers = Publisher::factory()->count(3)->create();

    livewire(ListPublishers::class)->assertCanSeeTableRecords($publishers);
});

it('searches by name', function(): void {
    $anagrama = Publisher::factory()->create(['name' => 'Anagrama']);
    $other = Publisher::factory()->create(['name' => 'Alfaguara']);

    livewire(ListPublishers::class)
        ->searchTable('Anagrama')
        ->assertCanSeeTableRecords([$anagrama])
        ->assertCanNotSeeTableRecords([$other]);
});

it('counts the books each publisher has in the catalogue', function(): void {
    $publisher = Publisher::factory()->create();
    Book::factory()->count(2)->for($publisher)->create();

    livewire(ListPublishers::class)
        ->assertCanSeeTableRecords([$publisher])
        ->assertTableColumnStateSet('books_count', 2, $publisher);
});

it('filters down to the publishers that still have books', function(): void {
    $stocked = Publisher::factory()->create();
    Book::factory()->for($stocked)->create();
    $empty = Publisher::factory()->create();

    livewire(ListPublishers::class)
        ->filterTable('books', true)
        ->assertCanSeeTableRecords([$stocked])
        ->assertCanNotSeeTableRecords([$empty]);
});

it('creates a publisher, deriving the slug from the name', function(): void {
    livewire(CreatePublisher::class)
        ->fillForm([
            'name'    => 'Libros del Asteroide',
            'website' => 'https://www.librosdelasteroide.com',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $publisher = Publisher::firstWhere('name', 'Libros del Asteroide');

    expect($publisher)->not->toBeNull()
        ->and($publisher->slug)->toBe('libros-del-asteroide');
});

it('requires a name', function(): void {
    livewire(CreatePublisher::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('rejects a website that is not a URL', function(): void {
    livewire(CreatePublisher::class)
        ->fillForm(['name' => 'Acantilado', 'website' => 'acantilado'])
        ->call('create')
        ->assertHasFormErrors(['website' => 'url']);
});

it('refuses to shelve the same slug twice', function(): void {
    Publisher::factory()->create(['slug' => 'anagrama']);

    livewire(CreatePublisher::class)
        ->fillForm(['name' => 'Anagrama Duplicada', 'slug' => 'anagrama'])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);
});

it('edits a publisher', function(): void {
    $publisher = Publisher::factory()->create();

    livewire(EditPublisher::class, ['record' => $publisher->getRouteKey()])
        ->fillForm(['name' => 'Nombre corregido'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($publisher->refresh()->name)->toBe('Nombre corregido');
});

it('files the logotype in the media library', function(): void {
    $publisher = Publisher::factory()->create();

    livewire(EditPublisher::class, ['record' => $publisher->getRouteKey()])
        ->fillForm(['logo' => [UploadedFile::fake()->image('logo.png', 400, 400)]])
        ->call('save')
        ->assertHasNoFormErrors();

    $logo = $publisher->refresh()->getFirstMedia(Publisher::LOGO_COLLECTION);

    expect($logo)->not->toBeNull()
        ->and($logo->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($publisher->logoUrl('thumb'))->toContain('thumb');

    Storage::disk('public')->assertExists($logo->getPathRelativeToRoot());
});

/*
 | A publisher has one logotype, not a history of them: uploading a new one has
 | to leave the old file behind on the disk as well as out of the collection.
 */
it('replaces the logotype rather than collecting them', function(): void {
    $publisher = Publisher::factory()->create();
    $first = $publisher->addMediaFromString(fakeCover(400, 400))
        ->usingFileName('viejo.jpg')
        ->toMediaCollection(Publisher::LOGO_COLLECTION);

    livewire(EditPublisher::class, ['record' => $publisher->getRouteKey()])
        ->fillForm(['logo' => [UploadedFile::fake()->image('nuevo.png', 400, 400)]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($publisher->refresh()->getMedia(Publisher::LOGO_COLLECTION))->toHaveCount(1)
        ->and($publisher->getFirstMedia(Publisher::LOGO_COLLECTION)->id)->not->toBe($first->id);

    Storage::disk('public')->assertMissing($first->getPathRelativeToRoot());
});

it('shows the logotype in the listing', function(): void {
    $publisher = Publisher::factory()->create();
    $logo = $publisher->addMediaFromString(fakeCover(400, 400))
        ->usingFileName('logo.jpg')
        ->toMediaCollection(Publisher::LOGO_COLLECTION);

    livewire(ListPublishers::class)
        ->assertTableColumnStateSet('logo', [$logo->uuid], $publisher);
});
