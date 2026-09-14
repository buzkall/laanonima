<?php

namespace App\Policies;

use App\Models\Publisher;
use App\Models\User;

/**
 * The catalog's own records, and the shop's to keep.
 *
 * A publisher page is public -- `/editorial/{slug}` is open to anybody -- but
 * that page is not asked for here: the controller reads the record and the
 * policy only ever speaks for the panel. So every method is the same question,
 * and the answer is whoever runs the shop.
 *
 * Spelled out rather than left to the panel gate. Filament allows what a policy
 * does not mention, so a resource whose model has no `delete()` anywhere is open
 * to any account that reaches the panel -- and the panel is not the only way in
 * once a route, a command or a second panel starts authorizing the same model.
 */
class PublisherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBookseller();
    }

    public function view(User $user, Publisher $publisher): bool
    {
        return $user->isBookseller();
    }

    public function create(User $user): bool
    {
        return $user->isBookseller();
    }

    public function update(User $user, Publisher $publisher): bool
    {
        return $user->isBookseller();
    }

    public function delete(User $user, Publisher $publisher): bool
    {
        return $user->isBookseller();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isBookseller();
    }

    /**
     * Fold duplicate publishers into one.
     *
     * Its own ability rather than `delete`, although a merge does delete the
     * absorbed rows: nothing is lost with them -- the books, the website, the
     * description and the logotype move to the survivor first -- so the demo's
     * catch on `delete` has no reason to reach it (see `DemoMode`).
     */
    public function merge(User $user, Publisher $publisher): bool
    {
        return $user->isBookseller();
    }
}
