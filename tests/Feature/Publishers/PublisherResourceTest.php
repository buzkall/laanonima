<?php

use App\Filament\Resources\Publishers\Pages\CreatePublisher;
use App\Filament\Resources\Publishers\Pages\EditPublisher;
use App\Filament\Resources\Publishers\Pages\ListPublishers;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
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
