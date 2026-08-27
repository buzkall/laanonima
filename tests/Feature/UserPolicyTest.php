<?php

use App\Models\User;

it('allows managing other users', function(string $ability): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect($user->can($ability, $other))->toBeTrue();
})->with(['view', 'update', 'delete']);

it('allows viewing the list and creating users', function(string $ability): void {
    $user = User::factory()->create();

    expect($user->can($ability, User::class))->toBeTrue();
})->with(['viewAny', 'create', 'deleteAny']);

it('never allows a user to delete their own account', function(): void {
    $user = User::factory()->create();

    expect($user->can('delete', $user))->toBeFalse();
});

it('still allows a user to update their own account', function(): void {
    $user = User::factory()->create();

    expect($user->can('update', $user))->toBeTrue();
});
