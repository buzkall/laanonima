<?php

use App\Models\User;

it('allows an administrator to manage other users', function(string $ability): void {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create();

    expect($admin->can($ability, $other))->toBeTrue();
})->with(['view', 'update', 'delete']);

it('allows an administrator to list and create users', function(string $ability): void {
    $admin = User::factory()->admin()->create();

    expect($admin->can($ability, User::class))->toBeTrue();
})->with(['viewAny', 'create', 'deleteAny']);

it('never allows an administrator to delete their own account', function(): void {
    $admin = User::factory()->admin()->create();

    expect($admin->can('delete', $admin))->toBeFalse();
});

it('still allows an administrator to update their own account', function(): void {
    $admin = User::factory()->admin()->create();

    expect($admin->can('update', $admin))->toBeTrue();
});

it('refuses a reader every user ability, over their own account too', function(string $ability): void {
    $reader = User::factory()->client()->create();
    $other = User::factory()->create();

    expect($reader->can($ability, $other))->toBeFalse()
        ->and($reader->can($ability, $reader))->toBeFalse();
})->with(['view', 'update', 'delete']);

it('refuses a reader the user list and creating users', function(string $ability): void {
    $reader = User::factory()->client()->create();

    expect($reader->can($ability, User::class))->toBeFalse();
})->with(['viewAny', 'create', 'deleteAny']);
