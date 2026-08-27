<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;

beforeEach(function(): void {
    $this->admin = User::factory()->admin()->create();

    $this->actingAs($this->admin);
});

it('lists users', function(): void {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords($users->push($this->admin));
});

it('searches users by name and email', function(string $attribute): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->searchTable($user->{$attribute})
        ->assertCanSeeTableRecords([$user])
        ->assertCanNotSeeTableRecords([$other]);
})->with(['name', 'email']);

it('filters users by email verification', function(): void {
    $verified = User::factory()->create();
    $unverified = User::factory()->unverified()->create();

    Livewire::test(ListUsers::class)
        ->filterTable('email_verified_at', true)
        ->assertCanSeeTableRecords([$verified])
        ->assertCanNotSeeTableRecords([$unverified])
        ->filterTable('email_verified_at', false)
        ->assertCanSeeTableRecords([$unverified])
        ->assertCanNotSeeTableRecords([$verified]);
});

it('filters users by role', function(): void {
    $client = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->filterTable('role', UserRole::Admin->value)
        ->assertCanSeeTableRecords([$this->admin])
        ->assertCanNotSeeTableRecords([$client])
        ->filterTable('role', UserRole::Client->value)
        ->assertCanSeeTableRecords([$client])
        ->assertCanNotSeeTableRecords([$this->admin]);
});

it('creates a user with a hashed password', function(): void {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name'     => 'Ada Lovelace',
            'email'    => 'ada@example.com',
            'password' => 'secret-password',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(User::class, [
        'name'  => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $user = User::query()->where('email', 'ada@example.com')->sole();

    expect($user->password)->not->toBe('secret-password')
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

it('defaults new users to the client role', function(): void {
    Livewire::test(CreateUser::class)
        ->assertSchemaStateSet(['role' => UserRole::Client]);
});

it('creates a user with the selected role', function(): void {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name'     => 'Grace Hopper',
            'email'    => 'grace@example.com',
            'password' => 'secret-password',
            'role'     => UserRole::Admin,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'grace@example.com')->sole())
        ->role->toBe(UserRole::Admin);
});

it('validates the create form', function(array $data, array $errors): void {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name'     => 'Ada Lovelace',
            'email'    => 'ada@example.com',
            'password' => 'secret-password',
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`name` is required'               => [['name' => null], ['name' => 'required']],
    '`name` is max 255 characters'     => [['name' => Str::random(256)], ['name' => 'max']],
    '`email` is required'              => [['email' => null], ['email' => 'required']],
    '`email` is a valid email address' => [['email' => 'not-an-email'], ['email' => 'email']],
    '`password` is required'           => [['password' => null], ['password' => 'required']],
    '`role` is required'               => [['role' => null], ['role' => 'required']],
]);

it('requires the email address to be unique', function(): void {
    $existing = User::factory()->create();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name'     => 'Ada Lovelace',
            'email'    => $existing->email,
            'password' => 'secret-password',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('keeps the existing password when the password field is left empty', function(): void {
    $user = User::factory()->create();
    $originalPassword = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->assertSchemaStateSet(['name' => $user->name, 'password' => null])
        ->fillForm(['name' => 'Renamed'])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    expect($user->refresh())
        ->name->toBe('Renamed')
        ->password->toBe($originalPassword);
});

it('updates the password when one is provided', function(): void {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['password' => 'brand-new-password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('brand-new-password', $user->refresh()->password))->toBeTrue();
});

it('allows the email address of the record being edited', function(): void {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('does not register a view page', function(): void {
    expect(UserResource::getPages())
        ->toHaveKeys(['index', 'create', 'edit'])
        ->not->toHaveKey('view');
});

it('capitalises the navigation label', function(): void {
    expect(UserResource::getNavigationLabel())->toBe('Usuarios');
});

it('hides the delete action for the authenticated user', function(): void {
    $other = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($this->admin))
        ->assertActionVisible(TestAction::make('delete')->table($other));
});

it('skips the authenticated user when bulk deleting', function(): void {
    $others = User::factory()->count(2)->create();

    Livewire::test(ListUsers::class)
        ->selectTableRecords([...$others->pluck('id')->all(), $this->admin->getKey()])
        ->callAction(TestAction::make('delete')->table()->bulk());

    assertModelExists($this->admin);

    $others->each(fn(User $user) => assertModelMissing($user));
});

it('renders the send magic link action on every row', function(): void {
    $other = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertActionVisible(TestAction::make('sendMagicLink')->table($other))
        ->assertSeeHtml("mountAction('sendMagicLink'");
});
