<?php

use App\Filament\Client\Resources\BookRequests\Pages\ListBookRequests;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\BookRequests\BookRequestResource;
use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Filament\Resources\Publishers\PublisherResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Book;
use App\Models\BookRequest;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Laravel\assertModelExists;
use function Pest\Livewire\livewire;

/**
 * The demo is off for the rest of the suite (see phpunit.xml), so every test
 * here turns it on for itself and the shop is tested as it really works
 * everywhere else.
 */
beforeEach(function(): void {
    config()->set('site.demo_mode', true);

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

/**
 * Every resource in the admin panel, including the three whose models have no
 * `delete()` in a policy to edit: Filament asks the gate's before callbacks
 * directly when the policy method is missing, so the refusal reaches them too.
 */
dataset('admin resources', [
    'books'      => [BookResource::class],
    'authors'    => [AuthorResource::class],
    'publishers' => [PublisherResource::class],
    'users'      => [UserResource::class],
    'requests'   => [BookRequestResource::class],
]);

it('refuses deletion on every resource, to an administrator too', function(string $resource): void {
    expect($resource::canDeleteAny())->toBeFalse()
        ->and($resource::canForceDeleteAny())->toBeFalse();
})->with('admin resources');

it('gives deletion back once the demo is over', function(string $resource): void {
    config()->set('site.demo_mode', false);

    expect($resource::canDeleteAny())->toBeTrue();
})->with('admin resources');

it('leaves reading and writing alone', function(): void {
    expect(BookResource::canViewAny())->toBeTrue()
        ->and(BookResource::canCreate())->toBeTrue()
        ->and(BookResource::canEdit(Book::factory()->create()))->toBeTrue();
});

it('takes the delete button off a listing and off an edit page', function(): void {
    $book = Book::factory()->create();

    livewire(ListBooks::class)
        ->assertActionHidden(TestAction::make('delete')->table()->bulk());

    livewire(EditBook::class, ['record' => $book->getRouteKey()])
        ->assertActionHidden('delete');

    assertModelExists($book);
});

it('takes the row delete action off the users table', function(): void {
    $reader = User::factory()->client()->create();

    livewire(ListUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($reader))
        ->assertActionHidden(TestAction::make('delete')->table()->bulk());
});

it('puts the delete buttons back once the demo is over', function(): void {
    config()->set('site.demo_mode', false);

    $reader = User::factory()->client()->create();

    livewire(ListUsers::class)
        ->assertActionVisible(TestAction::make('delete')->table($reader))
        ->assertActionVisible(TestAction::make('delete')->table()->bulk());
});

it('leaves a reader nothing to withdraw in the client panel', function(): void {
    Filament::setCurrentPanel('client');

    $reader = User::factory()->client()->create();
    $this->actingAs($reader);

    $mine = BookRequest::factory()->for($reader)->create();

    livewire(ListBookRequests::class)
        ->assertActionHidden(TestAction::make('withdraw')->table($mine));

    expect($reader->can('withdraw', $mine))->toBeFalse();
});
