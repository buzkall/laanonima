<?php

use App\Enums\BookRequestStatus;
use App\Enums\UserRole;
use App\Filament\Resources\BookRequests\BookRequestResource;
use App\Mail\BookRequestReceived;
use App\Models\Book;
use App\Models\BookRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function(): void {
    Mail::fake();

    $this->reader = User::factory()->client()->create(['name' => 'Marta Ruiz', 'email' => 'marta@example.com']);
});

it('shows the form to a reader who is signed in', function(): void {
    $this->actingAs($this->reader)
        ->get(route('book-requests.create'))
        ->assertOk()
        ->assertSee(__('book_requests.public.heading'))
        ->assertSee(__('book_requests.public.submit'))
        ->assertSee('marta@example.com');
});

it('asks a stranger to sign in first', function(): void {
    $this->get(route('book-requests.create'))->assertRedirect(UserRole::Client->loginUrl());

    $book = Book::factory()->create();
    $this->get(route('book-requests.create.book', $book))->assertRedirect(UserRole::Client->loginUrl());

    $this->post(route('book-requests.store'), ['title' => 'El maestro y Margarita'])
        ->assertRedirect(UserRole::Client->loginUrl());

    expect(BookRequest::count())->toBe(0);
});

it('remembers the form a stranger was sent away from', function(): void {
    $this->get(route('book-requests.create'))->assertRedirect(UserRole::Client->loginUrl());

    expect(session('url.intended'))->toBe(route('book-requests.create'));
});

it('writes down the request and tells the shop about it', function(): void {
    $this->actingAs($this->reader)->post(route('book-requests.store'), [
        'title'     => 'El maestro y Margarita',
        'author'    => 'Mijaíl Bulgákov',
        'publisher' => 'Alianza',
        'isbn'      => '9788491046332',
        'notes'     => 'Me vale de segunda mano.',
    ])->assertRedirect(route('book-requests.create'));

    $request = BookRequest::sole();

    expect($request->title)->toBe('El maestro y Margarita')
        ->and($request->user->is($this->reader))->toBeTrue()
        ->and($request->status)->toBe(BookRequestStatus::Pending)
        ->and($request->book_id)->toBeNull();

    Mail::assertSent(BookRequestReceived::class, fn(BookRequestReceived $mail): bool => $mail->hasTo(config('site.contact_email'))
        && $mail->hasReplyTo('marta@example.com')
        && $mail->bookRequest->is($request));
});

it('tells the reader it has been noted', function(): void {
    $this->actingAs($this->reader)->post(route('book-requests.store'), [
        'title' => 'El maestro y Margarita',
    ]);

    $this->actingAs($this->reader)
        ->get(route('book-requests.create'))
        ->assertOk()
        ->assertSee(__('book_requests.public.sent.heading'))
        ->assertSee(__('book_requests.public.sent.body', ['title' => 'El maestro y Margarita']), false)
        ->assertDontSee(__('book_requests.public.submit'));
});

it('needs a title and nothing else', function(): void {
    $this->actingAs($this->reader)
        ->post(route('book-requests.store'), [])
        ->assertSessionHasErrors('title');

    expect(BookRequest::count())->toBe(0);
    Mail::assertNothingSent();

    $this->actingAs($this->reader)
        ->post(route('book-requests.store'), ['title' => 'El maestro y Margarita'])
        ->assertSessionHasNoErrors();

    expect(BookRequest::count())->toBe(1);
});

it('refuses an ISBN that is not one', function(): void {
    $this->actingAs($this->reader)->post(route('book-requests.store'), [
        'title' => 'El maestro y Margarita',
        'isbn'  => '1234567890123',
    ])->assertSessionHasErrors('isbn');

    expect(BookRequest::count())->toBe(0);
});

it('asks for a telephone only while the account has none', function(): void {
    $this->actingAs($this->reader)
        ->get(route('book-requests.create'))
        ->assertOk()
        ->assertSee(__('book_requests.public.phone_note'));

    $known = User::factory()->client()->withPhone()->create();

    $this->actingAs($known)
        ->get(route('book-requests.create'))
        ->assertOk()
        ->assertDontSee(__('book_requests.public.phone_note'));
});

it('keeps a telephone the account did not have on the account', function(): void {
    $this->actingAs($this->reader)->post(route('book-requests.store'), [
        'title' => 'El maestro y Margarita',
        'phone' => ' 600 100 200 ',
    ])->assertSessionHasNoErrors();

    expect($this->reader->refresh()->phone)->toBe('600 100 200');
});

it('never writes over a telephone the reader already gave us', function(): void {
    $known = User::factory()->client()->withPhone('611 222 333')->create();

    $this->actingAs($known)->post(route('book-requests.store'), [
        'title' => 'El maestro y Margarita',
        'phone' => '600 100 200',
    ]);

    expect($known->refresh()->phone)->toBe('611 222 333');
});

it('fills the form in from the book a reader came from', function(): void {
    $book = Book::factory()->create(['title' => 'Cuaderno de faros', 'stock' => 0]);

    $this->actingAs($this->reader)
        ->get(route('book-requests.create.book', $book))
        ->assertOk()
        ->assertSee('Cuaderno de faros')
        ->assertSee($book->isbn13)
        ->assertSee('value="' . $book->id . '"', false);
});

it('attaches the book the request was made from', function(): void {
    $book = Book::factory()->create();

    $this->actingAs($this->reader)->post(route('book-requests.store'), [
        'title'   => $book->title,
        'book_id' => $book->id,
    ]);

    expect(BookRequest::sole()->book->is($book))->toBeTrue();
});

it('sends a reader with a book we have run out of to the form', function(): void {
    $book = Book::factory()->create(['stock' => 0]);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee(route('book-requests.create.book', $book));
});

it('offers the form from every shelf', function(): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('book-requests.create'));
});

it('is a 404 to ask for a book that is not on the web yet', function(): void {
    $book = Book::factory()->create(['is_active' => false]);

    $this->actingAs($this->reader)
        ->get(route('book-requests.create.book', $book))
        ->assertNotFound();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('book-requests.create.book', $book))
        ->assertOk();
});

it('writes the shop a note it can act on', function(): void {
    $book = Book::factory()->create(['title' => 'Cuaderno de faros']);
    $reader = User::factory()->client()->withPhone('611 222 333')->create(['name' => 'Marta Ruiz']);

    $request = BookRequest::factory()->for($book)->for($reader)->create([
        'title' => 'Cuaderno de faros',
        'isbn'  => '9788417059552',
        'notes' => 'Me vale de segunda mano.',
    ]);

    $rendered = new BookRequestReceived($request)->render();

    expect($rendered)->toContain('Cuaderno de faros')
        ->and($rendered)->toContain('9788417059552')
        ->and($rendered)->toContain('611 222 333')
        ->and($rendered)->toContain('Me vale de segunda mano.')
        ->and($rendered)->toContain(route('books.show', $book))
        ->and($rendered)->toContain(BookRequestResource::getUrl('edit', ['record' => $request], panel: 'admin'));
});
