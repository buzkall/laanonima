<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Mail\BookRequestReceived;
use App\Models\Book;
use App\Models\BookRequest;
use App\Models\User;
use App\Support\CoverPalette;
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

        return view('books.request', [
            'book'    => $book,
            'palette' => CoverPalette::fromCover($book?->cover_color),
        ]);
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
            ->with('book_request_sent', $bookRequest->title);
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
