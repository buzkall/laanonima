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

/* The control is a button and not a link, so the only thing a request can
   check is what it was handed: the page's own address and the sentence that
   travels with it. What the browser does with them is `resources/js/share.js`. */
it('offers the imprint\'s shelf to be shared, with a line that names the shop', function(): void {
    $blackie = Publisher::factory()->create(['name' => 'Blackie Books', 'slug' => 'blackie-books']);
    Book::factory()->for($blackie)->create();

    $this->get(route('publishers.show', $blackie))
        ->assertOk()
        ->assertSee(__('books.public.share.action'))
        ->assertSeeHtml('data-share-url="' . e(route('publishers.show', $blackie)) . '"')
        ->assertSee(__('books.public.share.publisher_message', [
            'publisher' => 'Blackie Books',
            'shop'      => config('app.name'),
        ]));
});
