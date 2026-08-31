<?php

use App\Enums\BookRequestStatus;
use App\Filament\Resources\BookRequests\BookRequestResource;
use App\Filament\Resources\BookRequests\Pages\EditBookRequest;
use App\Filament\Resources\BookRequests\Pages\ListBookRequests;
use App\Models\Book;
use App\Models\BookRequest;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    $this->actingAs(User::factory()->admin()->create());
});

it('renders every requests panel page', function(): void {
    $request = BookRequest::factory()->create(['title' => 'El maestro y Margarita']);

    $this->get(BookRequestResource::getUrl('index'))->assertOk()->assertSee('El maestro y Margarita');
    $this->get(BookRequestResource::getUrl('create'))->assertOk();
    $this->get(BookRequestResource::getUrl('edit', ['record' => $request]))->assertOk();
});

it('lists the newest request first', function(): void {
    $old = BookRequest::factory()->create(['created_at' => now()->subWeek()]);
    $fresh = BookRequest::factory()->create(['created_at' => now()]);

    livewire(ListBookRequests::class)->assertCanSeeTableRecords([$fresh, $old], inOrder: true);
});

it('searches by the title asked for and by who asked', function(): void {
    $wanted = BookRequest::factory()
        ->for(User::factory()->client()->create(['name' => 'Marta Ruiz']))
        ->create(['title' => 'Cuaderno de faros']);

    $other = BookRequest::factory()
        ->for(User::factory()->client()->create(['name' => 'Juan Gil']))
        ->create(['title' => 'Muerte en Persia']);

    livewire(ListBookRequests::class)
        ->searchTable('Cuaderno de faros')
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other]);

    livewire(ListBookRequests::class)
        ->searchTable('Marta')
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other]);
});

it('filters by status', function(): void {
    $pending = BookRequest::factory()->create();
    $done = BookRequest::factory()->handled()->create();

    livewire(ListBookRequests::class)
        ->filterTable('status', [BookRequestStatus::Pending->value])
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$done]);
});

it('filters down to what is still open', function(): void {
    $open = BookRequest::factory()->create();
    $done = BookRequest::factory()->handled()->create();

    livewire(ListBookRequests::class)
        ->filterTable('open', true)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$done]);
});

it('shows who asked, with a way to reach them', function(): void {
    $reader = User::factory()->client()->withPhone('611 222 333')->create(['name' => 'Marta Ruiz']);
    $request = BookRequest::factory()->for($reader)->create();

    livewire(ListBookRequests::class)
        ->assertTableColumnStateSet('user.name', 'Marta Ruiz', $request)
        ->assertTableColumnStateSet('user.phone', '611 222 333', $request);
});

it('follows a request up to its status and internal notes', function(): void {
    $request = BookRequest::factory()->create();

    livewire(EditBookRequest::class, ['record' => $request->id])
        ->fillForm([
            'status'      => BookRequestStatus::Obtained->value,
            'admin_notes' => 'Pedido a la distribuidora el lunes.',
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    expect($request->refresh()->status)->toBe(BookRequestStatus::Obtained)
        ->and($request->admin_notes)->toBe('Pedido a la distribuidora el lunes.');
});

it('counts what is still open on the sidebar', function(): void {
    BookRequest::factory()->count(2)->create();
    BookRequest::factory()->handled()->create();

    expect(BookRequestResource::getNavigationBadge())->toBe('2');
});

it('shows the catalogued book a request came from', function(): void {
    $book = Book::factory()->create(['title' => 'Cuaderno de faros']);
    $request = BookRequest::factory()->for($book)->create();

    livewire(ListBookRequests::class)->assertTableColumnStateSet('book.title', 'Cuaderno de faros', $request);
});

it('labels the panel in Spanish', function(): void {
    app()->setLocale('es');

    $this->get(BookRequestResource::getUrl('create'))
        ->assertOk()
        ->assertSee('Qué nos piden', escape: false)
        ->assertSee('Quién lo pide', escape: false)
        ->assertSee('Notas internas', escape: false);
});
