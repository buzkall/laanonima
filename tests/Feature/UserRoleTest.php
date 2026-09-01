<?php

use App\Enums\UserRole;
use App\Models\User;

it('only has the admin and client roles', function(): void {
    expect(UserRole::cases())->toBe([UserRole::Admin, UserRole::Client]);
});

it('translates the role labels into Spanish', function(UserRole $role, string $label): void {
    expect($role->getLabel())->toBe($label);
})->with([
    'admin'  => [UserRole::Admin, 'Administrador'],
    'client' => [UserRole::Client, 'Cliente'],
]);

it('gives every role a colour and an icon', function(UserRole $role): void {
    expect($role->getColor())->not->toBeNull()
        ->and($role->getIcon())->not->toBeNull();
})->with(UserRole::cases());

it('casts the role column to the enum', function(): void {
    $user = User::factory()->admin()->create();

    expect($user->refresh()->role)->toBe(UserRole::Admin);
});

it('stores new users as clients by default', function(): void {
    $user = User::query()->create([
        'name'     => 'Ada Lovelace',
        'email'    => 'ada@example.com',
        'password' => 'secret-password',
    ]);

    expect($user->refresh()->role)->toBe(UserRole::Client);
});

it('filters users by role with the hasRole scope', function(): void {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->client()->create();

    expect(User::query()->hasRole(UserRole::Admin)->pluck('id')->all())->toBe([$admin->id])
        ->and(User::query()->hasRole(UserRole::Client)->pluck('id')->all())->toBe([$client->id]);
});
