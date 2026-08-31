<?php

use App\Models\Book;
use App\Models\Publisher;

it('lists everything on the shelf from one imprint', function(): void {
    $blackie = Publisher::factory()->create(['name' => 'Blackie Books', 'slug' => 'blackie-books']);
    $theirs = Book::factory()->for($blackie)->create(['title' => 'Cuaderno de faros']);
    Book::factory()->create(['title' => 'Muerte en Persia']);

    $this->get(route('publishers.show', $blackie))
        ->assertOk()
        ->assertSee('Blackie Books')
        ->assertSee('Cuaderno de faros')
        ->assertSee(route('books.show', $theirs))
        ->assertDontSee('Muerte en Persia');
});

it('never shows a book that is hidden from the web', function(): void {
    $blackie = Publisher::factory()->create();
    Book::factory()->for($blackie)->create(['title' => 'Todavía sin publicar', 'is_active' => false]);

    $this->get(route('publishers.show', $blackie))
        ->assertOk()
        ->assertDontSee('Todavía sin publicar')
        ->assertSee(__('books.public.publisher.empty', ['publisher' => $blackie->name]));
});

it('is a 404 for an imprint we do not stock', function(): void {
    $this->get(route('publishers.show', 'una-editorial-inventada'))->assertNotFound();
});

it('links to the imprint from the book page', function(): void {
    $blackie = Publisher::factory()->create(['name' => 'Blackie Books']);
    $book = Book::factory()->for($blackie)->create();

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee(route('publishers.show', $blackie));
});
