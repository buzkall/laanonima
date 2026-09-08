<?php

use App\Filament\Resources\BookRequests\BookRequestResource;
use App\Filament\Widgets\LatestBookRequests;
use App\Models\BookRequest;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('shows the five newest requests, newest first', function(): void {
    $older = BookRequest::factory()
        ->count(6)
        ->sequence(fn($sequence): array => [
            'title'      => "Petición {$sequence->index}",
            'created_at' => now()->subDays(10 - $sequence->index),
        ])
        ->create();

    Filament::setCurrentPanel('admin');

    Livewire::test(LatestBookRequests::class)
        ->assertCanSeeTableRecords($older->slice(1))
        ->assertCanNotSeeTableRecords([$older->first()])
        ->assertSeeInOrder(['Petición 5', 'Petición 1']);
});

it('sends a bookseller to the request and to the whole listing', function(): void {
    $request = BookRequest::factory()->create();

    Filament::setCurrentPanel('admin');

    Livewire::test(LatestBookRequests::class)
        ->assertTableActionHasUrl('edit', BookRequestResource::getUrl('edit', ['record' => $request]), record: $request)
        ->assertSee(__('widgets.requests.all'));
});

it('says so when nobody has asked for anything', function(): void {
    Filament::setCurrentPanel('admin');

    Livewire::test(LatestBookRequests::class)->assertSee(__('widgets.requests.empty'));
});

/*
 | Discovered out of app/Filament/Widgets, which only the admin panel reads.
 */
it('stands on the admin dashboard and nowhere else', function(): void {
    expect(Filament::getPanel('admin')->getWidgets())->toContain(LatestBookRequests::class)
        ->and(Filament::getPanel('client')->getWidgets())->not->toContain(LatestBookRequests::class);
});
