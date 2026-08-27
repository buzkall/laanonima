<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lets administrators into the admin panel', function() {
    $admin = User::factory()->admin()->create();

    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();

    actingAs($admin)
        ->get('/admin/users')
        ->assertOk();
});

it('keeps clients out of the admin panel', function() {
    $client = User::factory()->create();

    expect($client->role)->toBe(UserRole::Client)
        ->and($client->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    actingAs($client)
        ->get('/admin/users')
        ->assertForbidden();
});

it('redirects guests to the login page', function() {
    get('/admin/users')->assertRedirect('/admin/login');
});
