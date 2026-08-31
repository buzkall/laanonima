<?php

use App\Models\Book;

it('lists everything on the shelf by one author', function(): void {
    $hers = Book::factory()->create([
        'title'        => 'Las tres bodas de Manolita',
        'contributors' => [['name' => 'Almudena Grandes', 'role' => 'autor']],
    ]);
    Book::factory()->create([
        'title'        => 'La conjura de los necios',
        'contributors' => [['name' => 'John Kennedy Toole', 'role' => 'autor']],
    ]);

    $this->get(route('authors.show', 'almudena-grandes'))
        ->assertOk()
        ->assertSee('Almudena Grandes')
        ->assertSee('Las tres bodas de Manolita')
        ->assertSee(route('books.show', $hers))
        ->assertDontSee('La conjura de los necios');
});

it('writes the name the way it is printed on the book, accents and all', function(): void {
    Book::factory()->create(['contributors' => [['name' => 'Ramón J. Sénder', 'role' => 'autor']]]);

    $this->get(route('authors.show', 'ramon-j-sender'))
        ->assertOk()
        ->assertSee('Ramón J. Sénder');
});

it('finds a co-writer from either name', function(): void {
    Book::factory()->create([
        'title'        => 'Instrucción de novicias',
        'contributors' => [
            ['name' => 'Ana Garriga', 'role' => 'autor'],
            ['name' => 'Carmen Urbita', 'role' => 'autor'],
        ],
    ]);

    foreach (['ana-garriga', 'carmen-urbita'] as $slug) {
        $this->get(route('authors.show', $slug))
            ->assertOk()
            ->assertSee('Instrucción de novicias');
    }
});

it('keeps translators off the author shelf', function(): void {
    Book::factory()->create([
        'contributors' => [
            ['name' => 'Gillian Anderson', 'role' => 'autor'],
            ['name' => 'Esther Cruz Santaella', 'role' => 'traductor'],
        ],
    ]);

    $this->get(route('authors.show', 'esther-cruz-santaella'))->assertNotFound();
});

it('never shows a book that is hidden from the web', function(): void {
    Book::factory()->create([
        'title'        => 'Todavía sin publicar',
        'is_active'    => false,
        'contributors' => [['name' => 'Almudena Grandes', 'role' => 'autor']],
    ]);

    $this->get(route('authors.show', 'almudena-grandes'))->assertNotFound();
});

it('is a 404 for an author we have nothing by', function(): void {
    $this->get(route('authors.show', 'nadie-en-absoluto'))->assertNotFound();
});

it('links to the author from the book page', function(): void {
    $book = Book::factory()->create([
        'contributors' => [
            ['name' => 'Ana Garriga', 'role' => 'autor'],
            ['name' => 'Esther Cruz Santaella', 'role' => 'traductor'],
        ],
    ]);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee(route('authors.show', 'ana-garriga'))
        ->assertDontSee(route('authors.show', 'esther-cruz-santaella'));
});

it('keeps the slugs in step with the contributors when a book is edited', function(): void {
    $book = Book::factory()->create(['contributors' => [['name' => 'Ana Garriga', 'role' => 'autor']]]);

    expect($book->author_slugs)->toBe(['ana-garriga']);

    $book->update(['contributors' => [['name' => 'Carmen Urbita', 'role' => 'autor']]]);

    expect($book->refresh()->author_slugs)->toBe(['carmen-urbita']);

    $this->get(route('authors.show', 'ana-garriga'))->assertNotFound();
    $this->get(route('authors.show', 'carmen-urbita'))->assertOk();
});
