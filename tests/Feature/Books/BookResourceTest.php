<?php

use App\Enums\BookAvailability;
use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use App\Rules\Isbn;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    $this->actingAs(User::factory()->create());
    config()->set('books.metadata.google_books.key');
});

it('lists books', function(): void {
    $books = Book::factory()->count(3)->create();

    livewire(ListBooks::class)->assertCanSeeTableRecords($books);
});

it('searches by author, not just by title', function(): void {
    $book = Book::factory()->create([
        'contributors' => [['name' => 'Almudena Grandes', 'role' => 'autor']],
    ]);
    $other = Book::factory()->create();

    livewire(ListBooks::class)
        ->searchTable('Almudena')
        ->assertCanSeeTableRecords([$book])
        ->assertCanNotSeeTableRecords([$other]);
});

it('searches by ISBN, shown under the title', function(): void {
    $book = Book::factory()->create(['isbn13' => '9788433920423']);
    $other = Book::factory()->create(['isbn13' => '9788420412146']);

    livewire(ListBooks::class)
        ->searchTable('9788433920423')
        ->assertCanSeeTableRecords([$book])
        ->assertCanNotSeeTableRecords([$other]);
});

it('searches by publisher, shown under the author', function(): void {
    $anagrama = Publisher::factory()->create(['name' => 'Anagrama']);
    $book = Book::factory()->for($anagrama)->create();
    $other = Book::factory()->for(Publisher::factory()->create(['name' => 'Alfaguara']))->create();

    livewire(ListBooks::class)
        ->searchTable('Anagrama')
        ->assertCanSeeTableRecords([$book])
        ->assertCanNotSeeTableRecords([$other]);
});

it('creates a book', function(): void {
    $publisher = Publisher::factory()->create();

    livewire(CreateBook::class)
        ->fillForm([
            'isbn13'                 => '9788433920423',
            'title'                  => 'La conjura de los necios',
            'publisher_id'           => $publisher->id,
            'language'               => 'spa',
            'country_of_publication' => 'ES',
            'availability'           => BookAvailability::Disponible->value,
            'vat_rate'               => 4,
            'stock'                  => 3,
            'price_cents'            => '12.90',
            'contributors'           => [['name' => 'John Kennedy Toole', 'role' => 'autor']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $book = Book::firstWhere('isbn13', '9788433920423');

    expect($book->title)->toBe('La conjura de los necios')
        ->and($book->price_cents)->toBe(1290)
        ->and($book->authors_line)->toBe('John Kennedy Toole')
        ->and($book->slug)->toBe('la-conjura-de-los-necios-9788433920423');
});

it('rejects an ISBN whose check digit does not add up', function(): void {
    livewire(CreateBook::class)
        ->fillForm(['isbn13' => '9788433920424', 'title' => 'Cualquier cosa'])
        ->call('create')
        ->assertHasFormErrors(['isbn13']);
});

it('explains a rejected ISBN instead of printing a translation key', function(string $locale): void {
    App::setLocale($locale);

    $message = Validator::make(['isbn13' => '9788433920424'], ['isbn13' => new Isbn])
        ->errors()
        ->first('isbn13');

    expect($message)->not->toBe('validation.isbn')
        ->and($message)->toContain('ISBN');
})->with(['es', 'en']);

it('requires an ISBN and a title', function(): void {
    livewire(CreateBook::class)
        ->fillForm(['isbn13' => null, 'title' => null])
        ->call('create')
        ->assertHasFormErrors(['isbn13' => 'required', 'title' => 'required']);
});

it('refuses to shelve the same ISBN twice', function(): void {
    Book::factory()->create(['isbn13' => '9788433920423']);

    livewire(CreateBook::class)
        ->fillForm(['isbn13' => '9788433920423', 'title' => 'Duplicado'])
        ->call('create')
        ->assertHasFormErrors(['isbn13' => 'unique']);
});

it('edits a book', function(): void {
    $book = Book::factory()->create();

    livewire(EditBook::class, ['record' => $book->getRouteKey()])
        ->fillForm(['title' => 'Título corregido'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($book->refresh()->title)->toBe('Título corregido');
});

it('shows the price in euros while storing it in cents', function(): void {
    $book = Book::factory()->create(['price_cents' => 1290]);

    livewire(EditBook::class, ['record' => $book->getRouteKey()])
        ->assertFormSet(['price_cents' => '12.90']);
});

describe('the ISBN lookup', function(): void {
    it('fills the form from the metadata providers', function(): void {
        Http::fake([
            'openlibrary.org/api/books*' => Http::response(apiFixture('book-metadata/open-library-hit')),
            'covers.openlibrary.org/*'   => Http::response('', 404),
        ]);

        livewire(CreateBook::class)
            ->fillForm(['isbn13' => '978-84-339-2042-3'])
            ->callFormComponentAction('isbn13', 'lookup')
            ->assertHasNoFormErrors()
            ->assertFormSet([
                'title'          => 'La conjura de los necios',
                'pages'          => 365,
                'published_year' => 2002,
                'legal_deposit'  => 'B. 46240-2002',
            ])
            ->assertNotified();
    });

    it('creates the publisher it found, so it is a real relationship', function(): void {
        Http::fake([
            'openlibrary.org/api/books*' => Http::response(apiFixture('book-metadata/open-library-hit')),
            'covers.openlibrary.org/*'   => Http::response('', 404),
        ]);

        livewire(CreateBook::class)
            ->fillForm(['isbn13' => '9788433920423'])
            ->callFormComponentAction('isbn13', 'lookup');

        expect(Publisher::firstWhere('name', 'Anagrama'))->not->toBeNull();
    });

    it('asks the bookseller to type it in when nothing is found', function(): void {
        Http::fake(['openlibrary.org/*' => Http::response(apiFixture('book-metadata/open-library-miss'))]);

        livewire(CreateBook::class)
            ->fillForm(['isbn13' => '9788401352836'])
            ->callFormComponentAction('isbn13', 'lookup')
            ->assertHasNoFormErrors()
            ->assertFormSet(['title' => null])
            ->assertNotified();
    });

    it('does not blank out what the bookseller already typed', function(): void {
        Http::fake([
            'openlibrary.org/api/books*' => Http::response(apiFixture('book-metadata/open-library-miss')),
        ]);

        livewire(CreateBook::class)
            ->fillForm(['isbn13' => '9788401352836', 'title' => 'Escrito a mano'])
            ->callFormComponentAction('isbn13', 'lookup')
            ->assertFormSet(['title' => 'Escrito a mano']);
    });

    it('saves where the metadata came from, and when', function(): void {
        Http::fake([
            'openlibrary.org/api/books*' => Http::response(apiFixture('book-metadata/open-library-hit')),
            'covers.openlibrary.org/*'   => Http::response('', 404),
        ]);

        livewire(CreateBook::class)
            ->fillForm(['isbn13' => '9788433920423'])
            ->callFormComponentAction('isbn13', 'lookup')
            ->call('create')
            ->assertHasNoFormErrors();

        $book = Book::firstWhere('isbn13', '9788433920423');

        expect($book->metadata_source)->toBe('open_library')
            ->and($book->metadata_synced_at)->not->toBeNull()
            ->and($book->cover_source_url)->toContain('covers.openlibrary.org');
    });

    it('says so when the ISBN itself is wrong, without calling anyone', function(): void {
        Http::fake();

        livewire(CreateBook::class)
            ->fillForm(['isbn13' => '9788433920424'])
            ->callFormComponentAction('isbn13', 'lookup')
            ->assertNotified();

        Http::assertNothingSent();
    });
});
