<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Publisher;

/**
 * A book the search must not find, with every field it matches against
 * written out -- see `.ai/rules/tests.md`.
 */
function searchDecoy(array $attributes = []): Book
{
    return Book::factory()
        ->for(Publisher::factory()->create(['name' => 'Editorial Lejana', 'slug' => 'editorial-lejana']))
        ->create([
            'title'          => 'Muerte en Persia',
            'subtitle'       => null,
            'original_title' => null,
            'isbn13'         => '9780000000002',
            'isbn10'         => null,
            'contributors'   => [['name' => 'Annemarie Schwarzenbach', 'role' => 'author']],
            ...$attributes,
        ]);
}

it('finds a book by its title whatever the case and accents', function(): void {
    Book::factory()->create(['title' => 'Cien años de soledad']);
    searchDecoy();

    $this->get(route('books.search', ['q' => 'CIEN AÑOS']))
        ->assertSee('Cien años de soledad')
        ->assertDontSee('Muerte en Persia');
});

it('finds a book by the people and the imprint behind it', function(string $query): void {
    Book::factory()
        ->for(Publisher::factory()->create(['name' => 'Libros del Asteroide', 'slug' => 'libros-del-asteroide']))
        ->create([
            'title'        => 'Stoner',
            'contributors' => [
                ['name' => 'John Williams', 'role' => 'author'],
                ['name' => 'Lucía Barahona', 'role' => 'translator'],
            ],
        ]);
    searchDecoy();

    $this->get(route('books.search', ['q' => $query]))
        ->assertSee('Stoner')
        ->assertDontSee('Muerte en Persia');
})->with([
    'author'     => 'williams',
    'translator' => 'lucia barahona',
    'publisher'  => 'asteroide',
]);

it('finds a book by an ISBN typed with hyphens', function(): void {
    Book::factory()->create(['title' => 'Stoner', 'isbn13' => '9788415578796']);
    searchDecoy();

    $this->get(route('books.search', ['q' => '978-84-15578-79-6']))
        ->assertSee('Stoner')
        ->assertDontSee('Muerte en Persia');
});

it('only lists books that match every word', function(): void {
    Book::factory()->create([
        'title'        => 'Cien años de soledad',
        'contributors' => [['name' => 'Gabriel García Márquez', 'role' => 'author']],
    ]);
    searchDecoy(['title' => 'Cien cartas', 'contributors' => [['name' => 'Juan Pérez', 'role' => 'author']]]);

    $this->get(route('books.search', ['q' => 'marquez cien']))
        ->assertSee('Cien años de soledad')
        ->assertDontSee('Cien cartas');
});

it('never shows a book that is hidden from the web', function(): void {
    Book::factory()->create(['title' => 'Todavía sin publicar', 'is_active' => false]);

    $this->get(route('books.search', ['q' => 'todavia']))
        ->assertDontSee('Todavía sin publicar')
        ->assertSee(__('books.public.search.empty', ['query' => 'todavia']));
});

it('keeps the query in the pagination links', function(): void {
    config(['site.shelf.per_page' => 1]);
    Book::factory()->count(2)->sequence(['title' => 'Faro uno'], ['title' => 'Faro dos'])->create();

    $this->get(route('books.search', ['q' => 'faro']))
        ->assertSee(route('books.search', ['q' => 'faro', 'page' => 2]));
});

it('asks for a query rather than listing the whole shelf', function(string $query): void {
    Book::factory()->create(['title' => 'Cuaderno de faros']);

    $this->get(route('books.search', ['q' => $query]))
        ->assertSee(__('books.public.search.prompt'))
        ->assertDontSee('Cuaderno de faros');
})->with(['empty' => '', 'blank' => '   ']);

it('finds nothing for a query made only of punctuation', function(): void {
    Book::factory()->create(['title' => 'Cuaderno de faros']);

    $this->get(route('books.search', ['q' => '%_-']))
        ->assertDontSee('Cuaderno de faros')
        ->assertSee(__('books.public.search.empty', ['query' => '%_-']));
});

it('follows an author who is renamed', function(): void {
    Book::factory()->create([
        'title'        => 'Stoner',
        'contributors' => [['name' => 'John Williams', 'role' => 'author']],
    ]);

    Author::query()->where('name', 'John Williams')->sole()->update(['name' => 'John Edward Williams']);

    $this->get(route('books.search', ['q' => 'edward']))->assertSee('Stoner');
});

it('follows a publisher that is renamed', function(): void {
    $publisher = Publisher::factory()->create(['name' => 'Libros del Asteroide', 'slug' => 'libros-del-asteroide']);
    Book::factory()->for($publisher)->create(['title' => 'Stoner']);

    $publisher->update(['name' => 'Asteroide Ediciones']);

    $this->get(route('books.search', ['q' => 'ediciones']))->assertSee('Stoner');
});

it('says it found nothing for a misspelling when the database cannot guess', function(): void {
    Book::factory()->create(['title' => 'Instrucción de novicias']);

    $this->get(route('books.search', ['q' => 'intruccion']))
        ->assertDontSee('Instrucción de novicias')
        ->assertDontSee(__('books.public.search.resembling', ['query' => 'intruccion']))
        ->assertSee(__('books.public.search.empty', ['query' => 'intruccion']));
});
