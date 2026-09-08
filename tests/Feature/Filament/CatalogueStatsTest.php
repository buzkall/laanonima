<?php

use App\Filament\Widgets\CatalogueStats;
use App\Models\Book;
use App\Models\BookRequest;
use App\Models\Subject;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('counts the whole catalogue and what of it is on the web', function(): void {
    Book::factory()->count(3)->create();
    Book::factory()->create(['is_active' => false]);

    Filament::setCurrentPanel('admin');

    Livewire::test(CatalogueStats::class)
        ->assertSee(__('widgets.catalogue.books'))
        ->assertSee('4')
        ->assertSee(trans_choice('widgets.catalogue.books_online', 3, ['count' => 3]));
});

it('counts the requests still open', function(): void {
    BookRequest::factory()->count(2)->create();
    BookRequest::factory()->handled()->create();

    Filament::setCurrentPanel('admin');

    Livewire::test(CatalogueStats::class)
        ->assertSee(__('widgets.catalogue.requests'))
        ->assertSee(trans_choice('widgets.catalogue.requests_open', 2, ['count' => 2]));
});

it('leads the materias card with what is filed rather than the whole THEMA scheme', function(): void {
    $filed = Subject::factory()->count(2)->create();
    Subject::factory()->count(5)->create();

    $filed->each(fn(Subject $subject) => Book::factory()->create(['subject_id' => $subject->getKey()]));

    Filament::setCurrentPanel('admin');

    /* The whole card in one go: the label, then the two filed, then the scheme. */
    expect(Livewire::test(CatalogueStats::class)->html())
        ->toMatch('/Materias.*?stat-value.*?\b2\b.*?de 7 en el esquema THEMA/s');
});

/*
 | The widget is discovered out of app/Filament/Widgets, which only the admin
 | panel reads -- the client panel discovers app/Filament/Client/Widgets. The
 | dashboard itself cannot be asserted on: Filament renders a widget lazily, so
 | the page arrives holding a placeholder rather than the counts.
 */
it('stands on the admin dashboard and nowhere else', function(): void {
    expect(Filament::getPanel('admin')->getWidgets())->toContain(CatalogueStats::class)
        ->and(Filament::getPanel('client')->getWidgets())->not->toContain(CatalogueStats::class);
});
