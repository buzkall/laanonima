<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lets administrators into the admin panel', function(): void {
    $admin = User::factory()->admin()->create();

    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();

    actingAs($admin)
        ->get('/admin/users')
        ->assertOk();
});

it('keeps clients out of the admin panel', function(): void {
    $client = User::factory()->create();

    expect($client->role)->toBe(UserRole::Client)
        ->and($client->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    actingAs($client)
        ->get('/admin/users')
        ->assertForbidden();
});

it('lets clients into the client panel', function(): void {
    $client = User::factory()->create();

    expect($client->canAccessPanel(Filament::getPanel('client')))->toBeTrue();

    actingAs($client)
        ->get('/client')
        ->assertOk();
});

it('keeps administrators out of the client panel', function(): void {
    $admin = User::factory()->admin()->create();

    expect($admin->canAccessPanel(Filament::getPanel('client')))->toBeFalse();

    actingAs($admin)
        ->get('/client')
        ->assertForbidden();
});

it('redirects guests to the login page of each panel', function(string $url, string $login): void {
    get($url)->assertRedirect($login);
})->with([
    'admin'  => ['/admin/users', '/admin/login'],
    'client' => ['/client', '/client/login'],
]);
