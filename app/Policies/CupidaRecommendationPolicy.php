<?php

namespace App\Policies;

use App\Models\CupidaRecommendation;
use App\Models\User;

/**
 * A log the shop reads and nobody edits.
 *
 * A recommendation is something that already happened -- what a reader was
 * asked, what they said, and what they were handed -- so there is nothing here
 * to author and nothing to correct after the fact. Deleting is refused for the
 * same reason the rest is: what the shop spent and what it recommended is the
 * one record of both, and a row removed from it takes a question with it.
 *
 * Rows are still written, by `RecommendBook`, which does it as the app rather
 * than as a user and never asks this.
 */
class CupidaRecommendationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBookseller();
    }

    public function view(User $user, CupidaRecommendation $recommendation): bool
    {
        return $user->isBookseller();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CupidaRecommendation $recommendation): bool
    {
        return false;
    }

    public function delete(User $user, CupidaRecommendation $recommendation): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
