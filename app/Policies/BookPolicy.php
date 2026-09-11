<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Two audiences, one model. The shop window has one rule, and it is about the
 * record rather than the reader: a book is public once a bookseller has put it
 * on the web. Everything else here is the counter's, and says so.
 *
 * `view()` is the public one and the only method a guest ever reaches. The rest
 * exist because Filament allows what a policy does not mention: before they were
 * written, a book could be deleted by anyone who got as far as the panel, and a
 * `delete()` that is not there is not a rule anybody can read.
 */
class BookPolicy
{
    /**
     * A book still being catalogd is a 404, not a 403 -- a denial that says
     * "forbidden" confirms the address, and an unpublished book should not be
     * confirmed to anybody. A bookseller sees it anyway: the "Ver en la web"
     * action on the edit screen is most useful precisely while the record is
     * not ready to be published.
     *
     * The user is nullable because the shop window is mostly read by visitors
     * who are not signed in: a policy method typed `User` is skipped for a
     * guest and denies by default, which would hide the whole catalog.
     */
    public function view(?User $user, Book $book): Response
    {
        return $book->is_active || $user?->isBookseller()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function viewAny(User $user): bool
    {
        return $user->isBookseller();
    }

    public function create(User $user): bool
    {
        return $user->isBookseller();
    }

    public function update(User $user, Book $book): bool
    {
        return $user->isBookseller();
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->isBookseller();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isBookseller();
    }
}
