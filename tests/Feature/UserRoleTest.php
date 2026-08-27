<?php

use App\Enums\UserRole;
use App\Models\User;

it('only has the admin and client roles', function() {
    expect(UserRole::cases())->toBe([UserRole::Admin, UserRole::Client]);
});

it('translates the role labels into Spanish', function(UserRole $role, string $label) {
    expect($role->getLabel())->toBe($label);
})->with([
    'admin'  => [UserRole::Admin, 'Administrador'],
    'client' => [UserRole::Client, 'Cliente'],
]);

it('gives every role a colour and an icon', function(UserRole $role) {
    expect($role->getColor())->not->toBeNull()
        ->and($role->getIcon())->not->toBeNull();
})->with(UserRole::cases());

it('casts the role column to the enum', function() {
    $user = User::factory()->admin()->create();

    expect($user->refresh()->role)->toBe(UserRole::Admin);
});

it('stores new users as clients by default', function() {
    $user = User::query()->create([
        'name'     => 'Ada Lovelace',
        'email'    => 'ada@example.com',
        'password' => 'secret-password',
    ]);

    expect($user->refresh()->role)->toBe(UserRole::Client);
});
