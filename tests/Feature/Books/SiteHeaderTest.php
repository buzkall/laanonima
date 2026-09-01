<?php

use App\Models\Book;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Vite;

it('offers a visitor the client panel login', function(): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('books.public.login'))
        ->assertSee(Filament::getPanel('client')->getLoginUrl());
});

it('sends a signed-in client to their own panel', function(): void {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('books.public.account'))
        ->assertSee(Filament::getPanel('client')->getUrl())
        ->assertDontSee(__('books.public.login'));
});

it('sends a signed-in administrator to the admin panel', function(): void {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(Filament::getPanel('admin')->getUrl());
});

it('carries the login link on a book page too', function(): void {
    $book = Book::factory()->create(['title' => 'Cuaderno de faros']);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee(__('books.public.login'))
        ->assertSee(Filament::getPanel('client')->getLoginUrl());
});

it('wears the wordmark rather than the shop name in writing', function(): void {
    $book = Book::factory()->create(['title' => 'Cuaderno de faros']);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee(Vite::asset('resources/images/brand/la-anonima-logo.png'))
        ->assertSee('alt="' . config('app.name') . '"', escape: false);
});

it('no longer prints the tagline in the header', function(): void {
    $book = Book::factory()->create(['title' => 'Cuaderno de faros']);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertDontSee(__('books.public.tagline'));
});
