<?php

namespace App\Policies;

use App\Models\Author;
use App\Models\User;

/**
 * The catalog's own records, and the shop's to keep.
 *
 * An author page is public -- `/autor/{slug}` is open to anybody -- but that
 * page is not asked for here: the controller reads the record and the policy
 * only ever speaks for the panel. So every method is the same question, and
 * the answer is whoever runs the shop.
 *
 * Spelled out rather than left to the panel gate. Filament allows what a policy
 * does not mention, so a resource whose model has no `delete()` anywhere is open
 * to any account that reaches the panel -- and the panel is not the only way in
 * once a route, a command or a second panel starts authorizing the same model.
 */
class AuthorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBookseller();
    }

    public function view(User $user, Author $author): bool
    {
        return $user->isBookseller();
    }

    public function create(User $user): bool
    {
        return $user->isBookseller();
    }

    public function update(User $user, Author $author): bool
    {
        return $user->isBookseller();
    }

    public function delete(User $user, Author $author): bool
    {
        return $user->isBookseller();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isBookseller();
    }
}
