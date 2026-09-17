<?php

namespace App\Policies;

use App\Models\User;
use Filament\Support\Authorization\DenyResponse;
use Illuminate\Auth\Access\Response;

/**
 * Accounts are the shop's to manage, and only the shop's.
 *
 * The admin panel's gate already keeps readers away from the users resource,
 * but that gate is the panel's and not the model's: a second panel, a route or
 * a command authorizing a user would otherwise find every door open to any
 * account that can sign in. A reader edits their own details on their profile
 * page, which does not ask this policy.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBookseller();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isBookseller();
    }

    public function create(User $user): bool
    {
        return $user->isBookseller();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isBookseller();
    }

    public function delete(User $user, User $model): bool|Response
    {
        if (! $user->isBookseller()) {
            return false;
        }

        if ($user->isNot($model)) {
            return true;
        }

        return DenyResponse::make(
            'cannot_delete_self',
            message: function(int $failureCount, int $totalCount): string {
                if ($failureCount === $totalCount) {
                    return __('user.policy.cannot_delete_self.all');
                }

                return __('user.policy.cannot_delete_self.some', [
                    'count' => $failureCount,
                    'total' => $totalCount,
                ]);
            },
        );
    }

    public function deleteAny(User $user): bool
    {
        return $user->isBookseller();
    }
}
