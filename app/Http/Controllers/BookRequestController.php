<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Mail\BookRequestReceived;
use App\Models\Book;
use App\Models\BookRequest;
use App\Models\User;
use App\Support\CoverPalette;
use App\Support\Isbn;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class BookRequestController extends Controller
{
    /**
     * The form for a book we do not have on the shelf.
     *
     * The same form serves both ways in: a reader who arrives from a book page
     * gets that book written into it, and everybody else gets it empty. There
     * is no separate "out of stock" form, so the copy is the only thing that
     * changes between the two.
     *
     * Both are behind a sign-in. A request is worth nothing to the shop without
     * somebody to tell when the book turns up, and an account is where that
     * somebody lives.
     */
    public function create(?Book $book = null): View
    {
        if ($book instanceof Book) {
            Gate::authorize('view', $book);
        }

        /* The receipt for a request just sent takes the page over, so it is
           resolved here rather than read off a flashed string: it is what the
           page is about, and it brings a book with it. Scoped to the reader
           because the key is an id, and an id is guessable. */
        $sent = BookRequest::query()
            ->whereKey(session('book_request_sent'))
            ->whereBelongsTo(auth()->user())
            ->with('book')
            ->first();

        $sentBook = $sent->book ?? $this->catalogBookFor($sent);

        return view('books.request', [
            'book'     => $book,
            'sent'     => $sent,
            'sentBook' => $sentBook,
            'palette'  => CoverPalette::fromCover(($book ?? $sentBook)?->cover_color),
        ]);
    }

    /**
     * The book on our own shelves a request is about, when nobody attached one.
     *
     * A reader who filled the empty form in from the back cover of a book gave
     * us the one thing that identifies it, so the receipt can still show the
     * cover. `toIsbn13()` is what makes it worth trying: it takes the hyphens
     * out and lifts a 10-digit number off an older edition to the 13 the
     * catalog is keyed on.
     */
    private function catalogBookFor(?BookRequest $request): ?Book
    {
        $isbn13 = Isbn::toIsbn13($request?->isbn);

        return $isbn13 === null
            ? null
            : Book::query()->where('is_active', true)->firstWhere('isbn13', $isbn13);
    }

    /**
     * Take the request down and tell the shop about it.
     *
     * The mail is sent inline rather than queued: there is no worker in front
     * of this site, and a request nobody is told about is worse than a slow
     * response. A failure to send must not lose the row, so it is sent after
     * the record exists.
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $bookRequest = BookRequest::create([
            ...$request->safe()->except('phone'),
            'user_id' => $user->id,
        ]);

        $this->rememberPhone($user, $request->string('phone')->trim()->value());

        Mail::to(config('site.contact_email'))->send(new BookRequestReceived($bookRequest));

        return redirect()
            ->route('book-requests.create')
            ->with('book_request_sent', $bookRequest->id);
    }

    /**
     * Keep a telephone number the account did not have yet.
     *
     * The form only asks for one while the account is without it, so this is
     * the reader filling a gap rather than correcting a number. A number they
     * already gave us is theirs to change on their own account, not something a
     * book request may quietly overwrite.
     */
    private function rememberPhone(User $user, string $phone): void
    {
        if (blank($user->phone) && filled($phone)) {
            $user->update(['phone' => $phone]);
        }
    }
}
