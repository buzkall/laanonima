<?php

use App\Filament\Resources\Books\Pages\ListBooks;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;

use function Pest\Livewire\livewire;

/*
 | AppServiceProvider sets the house date format once, for every table, schema
 | and picker. These assertions are the only thing standing between that and a
 | silent revert to Filament's "M j, Y".
 */

it('formats table dates as d/m/Y', function(): void {
    $this->actingAs(User::factory()->admin()->create());

    $table = livewire(ListBooks::class)->instance()->getTable();

    expect($table->getDefaultDateDisplayFormat())->toBe('d/m/Y')
        ->and($table->getDefaultDateTimeDisplayFormat())->toBe('d/m/Y H:i');
});

it('formats picker dates as d/m/Y', function(): void {
    expect(DatePicker::make('published_on')->getDefaultDateDisplayFormat())
        ->toBe(AppServiceProvider::DATE_FORMAT)
        ->and(DateTimePicker::make('email_verified_at')->getDefaultDateTimeDisplayFormat())
        ->toBe(AppServiceProvider::DATE_TIME_FORMAT);
});
