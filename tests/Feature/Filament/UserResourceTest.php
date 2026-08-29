<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
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
            'name'                  => 'Ada Lovelace',
            'email'                 => 'ada@example.com',
            'password'              => 'Sup3r-Secret-Passw0rd',
            'password_confirmation' => 'Sup3r-Secret-Passw0rd',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(User::class, [
        'name'  => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $user = User::query()->where('email', 'ada@example.com')->sole();

    expect($user->password)->not->toBe('Sup3r-Secret-Passw0rd')
        ->and(Hash::check('Sup3r-Secret-Passw0rd', $user->password))->toBeTrue();
});

it('does not store the password confirmation', function(): void {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name'                  => 'Ada Lovelace',
            'email'                 => 'ada@example.com',
            'password'              => 'Sup3r-Secret-Passw0rd',
            'password_confirmation' => 'Sup3r-Secret-Passw0rd',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'ada@example.com')->sole()->getAttributes())
        ->not->toHaveKey('password_confirmation');
});

it('generates a password that fills both fields and passes validation', function(): void {
    $component = Livewire::test(CreateUser::class)
        ->fillForm([
            'name'  => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ])
        ->callAction(TestAction::make('generatePassword')->schemaComponent('password'))
        ->assertNotified();

    $password = $component->get('data.password');

    expect($password)->toBeString()->toHaveLength(16)
        ->and($component->get('data.password_confirmation'))->toBe($password);

    $component->call('create')->assertHasNoFormErrors();

    expect(Hash::check($password, User::query()->where('email', 'ada@example.com')->sole()->password))->toBeTrue();
});

it('defaults new users to the client role', function(): void {
    Livewire::test(CreateUser::class)
        ->assertSchemaStateSet(['role' => UserRole::Client]);
});

it('creates a user with the selected role', function(): void {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name'                  => 'Grace Hopper',
            'email'                 => 'grace@example.com',
            'password'              => 'Sup3r-Secret-Passw0rd',
            'password_confirmation' => 'Sup3r-Secret-Passw0rd',
            'role'                  => UserRole::Admin,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'grace@example.com')->sole())
        ->role->toBe(UserRole::Admin);
});

it('validates the create form', function(array $data, array $errors): void {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name'                  => 'Ada Lovelace',
            'email'                 => 'ada@example.com',
            'password'              => 'Sup3r-Secret-Passw0rd',
            'password_confirmation' => 'Sup3r-Secret-Passw0rd',
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`name` is required'                  => [['name' => null], ['name' => 'required']],
    '`name` is max 255 characters'        => [['name' => Str::random(256)], ['name' => 'max']],
    '`email` is required'                 => [['email' => null], ['email' => 'required']],
    '`email` is a valid email address'    => [['email' => 'not-an-email'], ['email' => 'email']],
    '`password` is required'              => [['password' => null, 'password_confirmation' => null], ['password' => 'required']],
    '`password` is at least 12 chars'     => [['password' => 'Sh0rt-Pass', 'password_confirmation' => 'Sh0rt-Pass'], ['password']],
    '`password` needs mixed case'         => [['password' => 'sup3r-secret-passw0rd', 'password_confirmation' => 'sup3r-secret-passw0rd'], ['password']],
    '`password` needs a number'           => [['password' => 'Super-Secret-Password', 'password_confirmation' => 'Super-Secret-Password'], ['password']],
    '`password` must be confirmed'        => [['password_confirmation' => 'Other-Secret-Passw0rd'], ['password' => 'confirmed']],
    '`password_confirmation` is required' => [['password_confirmation' => null], ['password_confirmation' => 'required']],
    '`role` is required'                  => [['role' => null], ['role' => 'required']],
]);

it('requires the email address to be unique', function(): void {
    $existing = User::factory()->create();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name'                  => 'Ada Lovelace',
            'email'                 => $existing->email,
            'password'              => 'Sup3r-Secret-Passw0rd',
            'password_confirmation' => 'Sup3r-Secret-Passw0rd',
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
        ->fillForm([
            'password'              => 'Brand-New-Passw0rd',
            'password_confirmation' => 'Brand-New-Passw0rd',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('Brand-New-Passw0rd', $user->refresh()->password))->toBeTrue();
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

it('marks the email column as copyable with an icon', function(): void {
    $column = Livewire::test(ListUsers::class)
        ->instance()
        ->getTable()
        ->getColumn('email');

    expect($column->isCopyable(null))->toBeTrue()
        ->and($column->getIcon(null))->toBe(Heroicon::OutlinedClipboardDocument)
        ->and($column->getIconPosition())->toBe(IconPosition::After);
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
