<?php

use App\Filament\Resources\Books\Pages\ListBooks;
use App\Models\User;

use function Pest\Livewire\livewire;

/*
 | AppServiceProvider configures every table once: filters apply as soon as
 | they change, and rows alternate their background. These assertions catch a
 | silent revert to Filament's deferred, unstriped defaults.
 */

it('applies table filters without an apply button', function(): void {
    $this->actingAs(User::factory()->admin()->create());

    $table = livewire(ListBooks::class)->instance()->getTable();

    expect($table->hasDeferredFilters())->toBeFalse();
});

it('stripes table rows', function(): void {
    $this->actingAs(User::factory()->admin()->create());

    $table = livewire(ListBooks::class)->instance()->getTable();

    expect($table->isStriped())->toBeTrue();
});
