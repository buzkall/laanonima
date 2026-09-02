<?php

use App\Filament\Resources\Authors\AuthorResource;
use App\Models\Author;
use App\Models\User;

beforeEach(function(): void {
    $this->actingAs(User::factory()->admin()->create());
});

it('renders every authors panel page', function(): void {
    $author = Author::factory()->create(['name' => 'Almudena Grandes']);

    $this->get(AuthorResource::getUrl('index'))->assertOk()->assertSee('Almudena Grandes');
    $this->get(AuthorResource::getUrl('create'))->assertOk();
    $this->get(AuthorResource::getUrl('edit', ['record' => $author]))->assertOk();
});

it('labels the panel in Spanish', function(): void {
    app()->setLocale('es');

    $this->get(AuthorResource::getUrl('create'))
        ->assertOk()
        ->assertSee('Catálogo', escape: false)
        ->assertSee('Biografía', escape: false);
});

it('falls back to English for a locale we do not ship', function(): void {
    app()->setLocale('en');

    $this->get(AuthorResource::getUrl('create'))
        ->assertOk()
        ->assertSee('Biography');
});
