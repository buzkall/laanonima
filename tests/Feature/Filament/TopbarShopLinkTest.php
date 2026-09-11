<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;

/*
 | The panels are the only place in the app with no way back to the shop: the
 | link is an icon alone, so the accessible name is what pins it down.
 */
it('offers a way back to the shop from the topbar of every panel', function(UserRole $role): void {
    $this->actingAs(User::factory()->create(['role' => $role]));

    $this->get(Filament::getPanel($role->panelId())->getUrl())
        ->assertOk()
        ->assertSee('href="' . route('home') . '"', escape: false)
        ->assertSee('Ir a la web');
})->with([UserRole::Admin, UserRole::Client]);
