<?php

namespace App\Policies;

use App\Models\BookRequest;
use App\Models\User;

/**
 * Two audiences, one model. The shop works every request; a reader sees only
 * what they asked for, and the only thing they may change about it is to call
 * it off.
 */
class BookRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BookRequest $bookRequest): bool
    {
        return $user->isBookseller() || $bookRequest->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isBookseller();
    }

    public function update(User $user, BookRequest $bookRequest): bool
    {
        return $user->isBookseller();
    }

    public function delete(User $user, BookRequest $bookRequest): bool
    {
        return $user->isBookseller();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isBookseller();
    }

    /**
     * Calling a request off is the reader's own, and only while the shop could
     * still be acting on it: one already got or already dropped is history.
     */
    public function withdraw(User $user, BookRequest $bookRequest): bool
    {
        return $bookRequest->user_id === $user->id && $bookRequest->isOpen();
    }
}
