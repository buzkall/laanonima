<?php

use App\Models\Book;
use App\Support\CoverPalette;

it('lists the books that are visible on the web', function(): void {
    $onTheShelf = Book::factory()->create(['title' => 'Cuaderno de faros']);
    $hidden = Book::factory()->create(['title' => 'Todavía sin publicar', 'is_active' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Cuaderno de faros')
        ->assertSee(route('books.show', $onTheShelf))
        ->assertDontSee('Todavía sin publicar');
});

it('shows the author, the price and where to find each book', function(): void {
    Book::factory()->create([
        'title'        => 'Instrucción de novicias',
        'contributors' => [['name' => 'Ana Garriga', 'role' => 'autor']],
        'price_cents'  => 2200,
        'stock'        => 3,
    ]);
    Book::factory()->outOfStock()->create(['title' => 'La conjura de los necios']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Ana Garriga')
        ->assertSee('22,00')
        ->assertSee(__('books.public.home.in_stock'))
        ->assertSee(__('books.public.home.out_of_stock'));
});

it('leads the shelf with what the bookseller recommends', function(): void {
    Book::factory()->create(['title' => 'Uno cualquiera', 'published_on' => '2026-01-01']);
    Book::factory()->featured()->create(['title' => 'El recomendado', 'published_on' => '1999-01-01']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['El recomendado', 'Uno cualquiera'])
        ->assertSee(__('books.public.home.featured'));
});

it('sorts the rest by publication date, with the undated ones last', function(): void {
    Book::factory()->create(['title' => 'Sin fecha', 'published_on' => null]);
    Book::factory()->create(['title' => 'La vieja', 'published_on' => '1998-06-01']);
    Book::factory()->create(['title' => 'La reciente', 'published_on' => '2026-06-01']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['La reciente', 'La vieja', 'Sin fecha']);
});

it('paints the shelf in the house red', function(): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('--cover: ' . CoverPalette::FALLBACK, escape: false);
});

it('says so when there is nothing on the shelf yet', function(): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('books.public.home.empty'));
});

it('breaks a long shelf into pages', function(): void {
    Book::factory()->count(25)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('books.public.home.next'))
        ->assertDontSee(__('books.public.home.prev'));

    $this->get(route('home', ['page' => 2]))
        ->assertOk()
        ->assertSee(__('books.public.home.prev'))
        ->assertDontSee(__('books.public.home.next'));
});
