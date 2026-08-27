<?php

namespace App\Policies;

use App\Models\User;
use Filament\Support\Authorization\DenyResponse;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, User $model): bool
    {
        return true;
    }

    public function delete(User $user, User $model): bool|Response
    {
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
        return true;
    }
}
