<?php

use App\Enums\ContributorRole;
use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Authors\RelationManagers\BooksRelationManager;
use App\Filament\Resources\Books\BookResource;
use App\Models\Author;
use App\Models\Book;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    $this->actingAs(User::factory()->create());
});

it('lists authors', function(): void {
    $authors = Author::factory()->count(3)->create();

    livewire(ListAuthors::class)->assertCanSeeTableRecords($authors);
});

it('searches by name', function(): void {
    $grandes = Author::factory()->create(['name' => 'Almudena Grandes']);
    $other = Author::factory()->create(['name' => 'Javier Marías']);

    livewire(ListAuthors::class)
        ->searchTable('Almudena')
        ->assertCanSeeTableRecords([$grandes])
        ->assertCanNotSeeTableRecords([$other]);
});

it('counts the books each author has in the catalogue', function(): void {
    $author = Author::factory()->create();
    Book::factory()->count(2)->create(['contributors' => [['name' => $author->name, 'role' => 'author']]]);

    livewire(ListAuthors::class)
        ->assertCanSeeTableRecords([$author])
        ->assertTableColumnStateSet('books_count', 2, $author);
});

it('filters down to the authors that still have books', function(): void {
    $stocked = Author::factory()->create();
    Book::factory()->create(['contributors' => [['name' => $stocked->name, 'role' => 'translator']]]);
    $empty = Author::factory()->create();

    livewire(ListAuthors::class)
        ->filterTable('books', true)
        ->assertCanSeeTableRecords([$stocked])
        ->assertCanNotSeeTableRecords([$empty]);
});

it('creates an author, deriving the slug from the name', function(): void {
    livewire(CreateAuthor::class)
        ->fillForm([
            'name' => 'Almudena Grandes',
            'bio'  => '<p>Escritora <strong>madrileña</strong>.</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $author = Author::firstWhere('name', 'Almudena Grandes');

    expect($author)->not->toBeNull()
        ->and($author->slug)->toBe('almudena-grandes')
        ->and($author->bio)->toBe('<p>Escritora <strong>madrileña</strong>.</p>')
        ->and($author->bioExcerpt())->toBe('Escritora madrileña.');
});

it('requires a name', function(): void {
    livewire(CreateAuthor::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('refuses the same slug twice', function(): void {
    Author::factory()->create(['slug' => 'almudena-grandes']);

    livewire(CreateAuthor::class)
        ->fillForm(['name' => 'Almudena Grandes (bis)', 'slug' => 'almudena-grandes'])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);
});

it('edits an author', function(): void {
    $author = Author::factory()->create();

    livewire(EditAuthor::class, ['record' => $author->getRouteKey()])
        ->fillForm(['name' => 'Nombre corregido', 'bio' => 'Biografía corregida.'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($author->refresh()->name)->toBe('Nombre corregido')
        ->and($author->bioExcerpt())->toBe('Biografía corregida.');
});

it('takes the person off every title page when deleted, and keeps the books', function(): void {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['contributors' => [
        ['name' => $author->name, 'role' => 'author'],
        ['name' => 'Esther Cruz Santaella', 'role' => 'translator'],
    ]]);

    $author->delete();

    expect($book->refresh()->contributors)->toHaveCount(1)
        ->and($book->authors_line)->toBeNull();
});

it('lists every book the person had a hand in, and nothing else', function(): void {
    $author = Author::factory()->create();
    $own = [
        Book::factory()->create(['contributors' => [['name' => $author->name, 'role' => 'author']]]),
        Book::factory()->create(['contributors' => [['name' => $author->name, 'role' => 'translator']]]),
    ];
    $foreign = Book::factory()->create();

    livewire(BooksRelationManager::class, [
        'ownerRecord' => $author,
        'pageClass'   => EditAuthor::class,
    ])
        ->assertCanSeeTableRecords($own)
        ->assertCanNotSeeTableRecords([$foreign])
        ->assertTableFilterHidden('authors')
        ->assertTableFilterVisible('publisher');
});

it('says what the person did on each book listed', function(): void {
    $author = Author::factory()->create();
    $translated = Book::factory()->create(['contributors' => [
        ['name' => 'Frank Herbert', 'role' => 'author'],
        ['name' => $author->name, 'role' => 'translator'],
    ]]);

    livewire(BooksRelationManager::class, [
        'ownerRecord' => $author,
        'pageClass'   => EditAuthor::class,
    ])
        ->assertTableColumnStateSet('contribution', [ContributorRole::Translator->getLabel()], $translated);
});

it('sends the edit action of a listed book to the books resource', function(): void {
    $author = Author::factory()->create();
    $book = Book::factory()->create(['contributors' => [['name' => $author->name, 'role' => 'author']]]);

    livewire(BooksRelationManager::class, [
        'ownerRecord' => $author,
        'pageClass'   => EditAuthor::class,
    ])
        ->assertTableActionHasUrl('edit', BookResource::getUrl('edit', ['record' => $book]), record: $book);
});
