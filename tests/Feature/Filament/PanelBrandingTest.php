<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Vite;

it('wears the wordmark instead of the shop name in writing', function(UserRole $role): void {
    $this->actingAs(User::factory()->create(['role' => $role]));

    $this->get(Filament::getPanel($role->panelId())->getUrl())
        ->assertOk()
        ->assertSee(Vite::asset('resources/images/brand/la-anonima-logo.png'))
        ->assertSee(Vite::asset('resources/images/brand/la-anonima-logo-dark.png'));
})->with([UserRole::Admin, UserRole::Client]);

it('carries the shop favicon rather than Filament\'s', function(UserRole $role): void {
    $this->actingAs(User::factory()->create(['role' => $role]));

    $this->get(Filament::getPanel($role->panelId())->getUrl())
        ->assertOk()
        ->assertSee('<link rel="icon" href="' . asset('favicon.svg') . '" />', escape: false);
})->with([UserRole::Admin, UserRole::Client]);
