<?php

use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use App\Models\User;

beforeEach(function(): void {
    /*
     | The admin panel only admits users whose role maps to it, so the books
     | pages are reachable to an administrator and to nobody else.
     */
    $this->actingAs(User::factory()->admin()->create());
});

it('renders every books panel page', function(): void {
    $book = Book::factory()->create(['title' => 'La conjura de los necios']);

    $this->get(BookResource::getUrl('index'))->assertOk()->assertSee('La conjura de los necios');
    $this->get(BookResource::getUrl('create'))->assertOk();
    $this->get(BookResource::getUrl('edit', ['record' => $book]))->assertOk();
});

it('labels the panel in Spanish', function(): void {
    app()->setLocale('es');

    $this->get(BookResource::getUrl('create'))
        ->assertOk()
        ->assertSee('Catálogo', escape: false)
        ->assertSee('Encuadernación', escape: false)
        ->assertSee('Depósito legal', escape: false);
});

it('falls back to English for a locale we do not ship', function(): void {
    app()->setLocale('en');

    $this->get(BookResource::getUrl('create'))
        ->assertOk()
        ->assertSee('Legal deposit');
});

/*
 | The cover download has to be reachable from the page. It was not, once, and
 | a bookseller had no way to tell a provider with no cover from a broken one.
 */
it('offers the cover download on both book pages', function(): void {
    app()->setLocale('es');

    $book = Book::factory()->create();

    $this->get(BookResource::getUrl('create'))->assertOk()->assertSee('Descargar cubierta');
    $this->get(BookResource::getUrl('edit', ['record' => $book]))->assertOk()->assertSee('Descargar cubierta');
});
