<?php

use App\Enums\UserRole;
use App\Filament\Resources\BookRequests\BookRequestResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\RelationManagers\BookRequestsRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\BookRequest;
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
        ->assertCanSeeTableRecords($users);
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

it('splits users into one tab per role, clients first', function(): void {
    expect(array_keys(Livewire::test(ListUsers::class)->instance()->getTabs()))
        ->toBe([UserRole::Client->value, UserRole::Admin->value]);
});

it('shows the clients tab before anything is clicked', function(): void {
    $client = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$client])
        ->assertCanNotSeeTableRecords([$this->admin]);
});

it('scopes each tab to its own role', function(): void {
    $client = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->set('activeTab', UserRole::Admin->value)
        ->assertCanSeeTableRecords([$this->admin])
        ->assertCanNotSeeTableRecords([$client])
        ->set('activeTab', UserRole::Client->value)
        ->assertCanSeeTableRecords([$client])
        ->assertCanNotSeeTableRecords([$this->admin]);
});

it('counts the users of each role on its tab', function(): void {
    User::factory()->count(2)->create();

    $tabs = Livewire::test(ListUsers::class)->instance()->getTabs();

    expect($tabs[UserRole::Client->value]->getBadge())->toBe('2')
        ->and($tabs[UserRole::Admin->value]->getBadge())->toBe('1');
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
    $other = User::factory()->admin()->create();

    Livewire::test(ListUsers::class)
        ->set('activeTab', UserRole::Admin->value)
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

it('lists the book requests of a client under their account', function(): void {
    $client = User::factory()->create();
    $requests = BookRequest::factory()->count(2)->for($client)->create();
    $other = BookRequest::factory()->create();

    Livewire::test(BookRequestsRelationManager::class, [
        'ownerRecord' => $client,
        'pageClass'   => EditUser::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($requests)
        ->assertCanNotSeeTableRecords([$other]);
});

it('renders the book requests relation manager on a client account', function(): void {
    $client = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $client->getKey()])
        ->assertSeeLivewire(BookRequestsRelationManager::class);
});

it('does not offer book requests on an administrator account', function(): void {
    expect(BookRequestsRelationManager::canViewForRecord($this->admin, EditUser::class))->toBeFalse()
        ->and(BookRequestsRelationManager::canViewForRecord(User::factory()->create(), EditUser::class))->toBeTrue();
});

it('badges the client tab with the requests still open', function(): void {
    $client = User::factory()->create();

    expect(BookRequestsRelationManager::getBadge($client, EditUser::class))->toBeNull();

    BookRequest::factory()->count(2)->for($client)->create();
    BookRequest::factory()->handled()->for($client)->create();

    expect(BookRequestsRelationManager::getBadge($client->fresh(), EditUser::class))->toBe('2');
});

it('sends the edit action to the book request resource', function(): void {
    $client = User::factory()->create();
    $request = BookRequest::factory()->for($client)->create();

    Livewire::test(BookRequestsRelationManager::class, [
        'ownerRecord' => $client,
        'pageClass'   => EditUser::class,
    ])
        ->assertActionHasUrl(
            TestAction::make('edit')->table($request),
            BookRequestResource::getUrl('edit', ['record' => $request]),
        );
});

it('only shows the password confirmation once a password is being typed', function(): void {
    Livewire::test(CreateUser::class)
        ->assertSeeHtml("'fi-hidden': ! ((\$get('password') ?? '').length > 0)");
});

it('still demands a confirmation for whatever password was typed', function(): void {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name'                  => 'Ada Lovelace',
            'email'                 => 'ada@example.com',
            'password'              => 'Sup3r-Secret-Passw0rd',
            'password_confirmation' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['password_confirmation' => 'required']);
});

it('shows an unverified account as such in the section header', function(): void {
    $user = User::factory()->unverified()->create();

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->assertOk()
        ->assertSee(__('user.placeholders.not_verified'));
});

it('shows when a verified account was verified', function(): void {
    $user = User::factory()->create(['email_verified_at' => now()->subDay()]);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->assertOk()
        ->assertSee(__('user.badges.verified', ['date' => $user->email_verified_at->translatedFormat('d/m/Y')]));
});

it('does not let the verification date be edited', function(): void {
    $user = User::factory()->unverified()->create();

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['email_verified_at' => now()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->email_verified_at)->toBeNull();
});
