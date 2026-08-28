<?php

use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use App\Models\User;

beforeEach(function() {
    /*
     | The admin panel only admits users whose role maps to it, so the books
     | pages are reachable to an administrator and to nobody else.
     */
    $this->actingAs(User::factory()->admin()->create());
});

it('renders every books panel page', function() {
    $book = Book::factory()->create(['title' => 'La conjura de los necios']);

    $this->get(BookResource::getUrl('index'))->assertOk()->assertSee('La conjura de los necios');
    $this->get(BookResource::getUrl('create'))->assertOk();
    $this->get(BookResource::getUrl('edit', ['record' => $book]))->assertOk();
});

it('labels the panel in Spanish', function() {
    app()->setLocale('es');

    $this->get(BookResource::getUrl('create'))
        ->assertOk()
        ->assertSee('Catálogo', escape: false)
        ->assertSee('Encuadernación', escape: false)
        ->assertSee('Depósito legal', escape: false);
});

it('falls back to English for a locale we do not ship', function() {
    app()->setLocale('en');

    $this->get(BookResource::getUrl('create'))
        ->assertOk()
        ->assertSee('Legal deposit');
});
