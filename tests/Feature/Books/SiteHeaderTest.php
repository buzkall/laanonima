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

it('carries the nav links inline and inside the phone menu', function(): void {
    $page = str($this->get(route('home'))->assertOk()->getContent())
        ->between('<header', '</header>')
        ->toString();

    // Twice each: once in the bar a laptop reads, once in the disclosure a
    // phone opens. One of the two going missing is the failure this catches.
    expect(substr_count($page, route('books.shelf')))->toBe(2)
        ->and(substr_count($page, route('cupida')))->toBe(2)
        ->and($page)->toContain(__('books.public.menu'));
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

    $page = $this->get(route('books.show', $book))
        ->assertOk()
        ->getContent();

    // Scoped to the bar rather than the page: the footer line reads
    // ":name Librería · Madrid", so the tagline is a substring of it and a
    // page-wide assertDontSee() would fail on the footer instead.
    expect(str((string)$page)->between('<header', '</header>')->toString())
        ->not->toContain(__('books.public.tagline'));
});
