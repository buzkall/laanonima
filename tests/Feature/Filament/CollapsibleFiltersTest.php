<?php

use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\BookRequests\Pages\ListBookRequests;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Filament\Resources\Cupida\Pages\ListCupida;
use App\Filament\Resources\Publishers\Pages\ListPublishers;
use App\Models\User;
use Filament\Tables\Enums\FiltersLayout;

use function Pest\Livewire\livewire;

/*
 | Every listing that puts its filters above the table asks for the collapsible
 | layout, which is what draws the trigger button a phone or a portrait iPad
 | needs. The panel is opened back up from `lg` in the admin theme, so a
 | listing reverting to plain `AboveContent` would take the button away and
 | leave nothing to open on a small screen.
 */

it('collapses the filters above a listing', function(string $page): void {
    $this->actingAs(User::factory()->admin()->create());

    $table = livewire($page)->instance()->getTable();

    expect($table->getFiltersLayout())->toBe(FiltersLayout::AboveContentCollapsible);
})->with([
    ListAuthors::class,
    ListBookRequests::class,
    ListBooks::class,
    ListCupida::class,
    ListPublishers::class,
]);
