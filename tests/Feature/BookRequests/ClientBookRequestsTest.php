<?php

use App\Enums\BookRequestStatus;
use App\Filament\Client\Resources\BookRequests\BookRequestResource;
use App\Filament\Client\Resources\BookRequests\Pages\ListBookRequests;
use App\Mail\BookRequestWithdrawn;
use App\Models\BookRequest;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    Mail::fake();
    Filament::setCurrentPanel('client');

    $this->reader = User::factory()->client()->create();
    $this->actingAs($this->reader);
});

it('shows a reader their own orders and nobody else', function(): void {
    $mine = BookRequest::factory()->for($this->reader)->create(['title' => 'Cuaderno de faros']);
    $theirs = BookRequest::factory()->create(['title' => 'Muerte en Persia']);

    livewire(ListBookRequests::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('renders the client listing', function(): void {
    BookRequest::factory()->for($this->reader)->create(['title' => 'Cuaderno de faros']);

    $this->get(BookRequestResource::getUrl('index', panel: 'client'))
        ->assertOk()
        ->assertSee('Cuaderno de faros');
});

it('gives a reader no way to rewrite an order', function(): void {
    $mine = BookRequest::factory()->for($this->reader)->create();

    expect(BookRequestResource::getPages())->toHaveKeys(['index'])
        ->and(array_keys(BookRequestResource::getPages()))->toBe(['index'])
        ->and($this->reader->can('update', $mine))->toBeFalse()
        ->and($this->reader->can('delete', $mine))->toBeFalse()
        ->and($this->reader->can('create', BookRequest::class))->toBeFalse();
});

it('lets a reader call an order off and tells the shop', function(): void {
    $mine = BookRequest::factory()->for($this->reader)->create();

    livewire(ListBookRequests::class)
        ->callAction(TestAction::make('withdraw')->table($mine))
        ->assertNotified();

    expect($mine->refresh()->status)->toBe(BookRequestStatus::Descartado);

    Mail::assertSent(BookRequestWithdrawn::class, fn(BookRequestWithdrawn $mail): bool => $mail->hasTo(config('site.contact_email'))
        && $mail->bookRequest->is($mine));
});

it('has nothing left to call off once the shop is done with it', function(): void {
    $got = BookRequest::factory()->for($this->reader)->handled()->create();

    livewire(ListBookRequests::class)
        ->assertActionHidden(TestAction::make('withdraw')->table($got));

    expect($this->reader->can('withdraw', $got))->toBeFalse();
});

it('never lets a reader call off somebody else request', function(): void {
    $theirs = BookRequest::factory()->create();

    expect($this->reader->can('withdraw', $theirs))->toBeFalse()
        ->and($this->reader->can('view', $theirs))->toBeFalse();

    Mail::assertNothingSent();
});

it('writes the shop a note when an order is called off', function(): void {
    $mine = BookRequest::factory()->for($this->reader)->create(['title' => 'Cuaderno de faros']);

    $rendered = new BookRequestWithdrawn($mine)->render();

    expect($rendered)->toContain('Cuaderno de faros')
        ->and($rendered)->toContain($this->reader->email);
});

it('keeps the shop out of nobody else pages, and the reader out of the shop', function(): void {
    $admin = User::factory()->admin()->create();
    $request = BookRequest::factory()->for($this->reader)->create();

    expect($admin->can('view', $request))->toBeTrue()
        ->and($admin->can('update', $request))->toBeTrue()
        ->and($admin->can('withdraw', $request))->toBeFalse()
        ->and($this->reader->can('view', $request))->toBeTrue();
});
