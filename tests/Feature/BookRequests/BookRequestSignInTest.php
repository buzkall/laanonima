<?php

use App\Enums\UserRole;
use App\Models\Book;

/*
 | Asking us for a book is the only thing on the shop behind a sign-in, so a
 | guest who presses "pídenoslo" is bounced to a password box they never asked
 | for. The form is remembered and they are put back on it afterwards -- what
 | was missing was anybody saying so.
 */

it('tells a guest sent away from the form why it is asking them to sign in', function(): void {
    $this->get(route('book-requests.create'))->assertRedirect(UserRole::Client->loginUrl());

    $this->get(UserRole::Client->loginUrl())
        ->assertOk()
        ->assertSee(__('auth.book_request.reason'));
});

it('says the same on the way in from a book', function(): void {
    $book = Book::factory()->create(['stock' => 0]);

    $this->get(route('book-requests.create.book', $book))->assertRedirect(UserRole::Client->loginUrl());

    $this->get(UserRole::Client->loginUrl())
        ->assertOk()
        ->assertSee(__('auth.book_request.reason'));
});

/* A reader with no account opens one, and the way there is a link on the login
   page -- so the reason has to survive the trip rather than stop at the door. */
it('keeps saying it on the register page the login form sends them to', function(): void {
    $this->get(route('book-requests.create'))->assertRedirect(UserRole::Client->loginUrl());

    $this->get(route('filament.client.auth.register'))
        ->assertOk()
        ->assertSee(__('auth.book_request.reason'));
});

it('says nothing to somebody who came to sign in of their own accord', function(): void {
    $this->get(UserRole::Client->loginUrl())
        ->assertOk()
        ->assertDontSee(__('auth.book_request.reason'));
});

/* The panels share a session. A guest turned away from the admin panel is not a
   reader asking for a book, and the login form must not tell them they are. */
it('says nothing when the remembered page is a panel rather than the form', function(): void {
    $this->get('/admin')->assertRedirect();

    $this->get(UserRole::Client->loginUrl())
        ->assertOk()
        ->assertDontSee(__('auth.book_request.reason'));
});
