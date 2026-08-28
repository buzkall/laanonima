<?php

use App\Filament\Resources\Publishers\PublisherResource;
use App\Models\Publisher;
use App\Models\User;

beforeEach(function(): void {
    $this->actingAs(User::factory()->admin()->create());
});

it('renders every publishers panel page', function(): void {
    $publisher = Publisher::factory()->create(['name' => 'Libros del Asteroide']);

    $this->get(PublisherResource::getUrl('index'))->assertOk()->assertSee('Libros del Asteroide');
    $this->get(PublisherResource::getUrl('create'))->assertOk();
    $this->get(PublisherResource::getUrl('edit', ['record' => $publisher]))->assertOk();
});

it('labels the panel in Spanish', function(): void {
    app()->setLocale('es');

    $this->get(PublisherResource::getUrl('create'))
        ->assertOk()
        ->assertSee('Catálogo', escape: false)
        ->assertSee('Logotipo', escape: false)
        ->assertSee('Sitio web', escape: false);
});

it('falls back to English for a locale we do not ship', function(): void {
    app()->setLocale('en');

    $this->get(PublisherResource::getUrl('create'))
        ->assertOk()
        ->assertSee('Website');
});
