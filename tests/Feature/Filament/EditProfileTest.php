<?php

use App\Filament\Auth\EditProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function(): void {
    Filament::setCurrentPanel('client');

    $this->client = User::factory()->create(['password' => 'Curr3nt-Passw0rd']);

    $this->actingAs($this->client);
});

it('renders inside the panel, navigation and all', function(string $panel, string $path): void {
    Filament::setCurrentPanel($panel);

    $user = $panel === 'admin' ? User::factory()->admin()->create() : $this->client;

    $this->actingAs($user)
        ->get($path)
        ->assertOk()
        ->assertSee('fi-sidebar', escape: false)
        ->assertSee('fi-topbar', escape: false);

    expect(EditProfile::isSimple())->toBeFalse();
})->with([
    'admin panel'  => ['admin', '/admin/profile'],
    'client panel' => ['client', '/client/profile'],
]);

it('lays the account out in two columns, with the passwords on a row of their own', function(): void {
    $html = $this->get('/client/profile')->assertOk()->getContent();

    expect($html)
        ->toContain('--cols-lg: repeat(2, minmax(0, 1fr))')
        ->toContain('--col-start-lg: 1')
        ->not->toContain('fi-fo-field-label-col fi-inline');
});

it('holds a reader to the same password rule the panel uses', function(): void {
    Livewire::test(EditProfile::class)
        ->fillForm([
            'password'             => 'secret',
            'passwordConfirmation' => 'secret',
            'currentPassword'      => 'Curr3nt-Passw0rd',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);

    expect(Hash::check('Curr3nt-Passw0rd', $this->client->refresh()->password))->toBeTrue();
});

it('changes a reader password from the profile page', function(): void {
    Livewire::test(EditProfile::class)
        ->fillForm([
            'password'             => 'Sup3r-Secret-Passw0rd',
            'passwordConfirmation' => 'Sup3r-Secret-Passw0rd',
            'currentPassword'      => 'Curr3nt-Passw0rd',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('Sup3r-Secret-Passw0rd', $this->client->refresh()->password))->toBeTrue();
});

it('asks for the confirmation only once a password is being set', function(): void {
    Livewire::test(EditProfile::class)
        ->fillForm(['phone' => '600 100 200'])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(EditProfile::class)
        ->fillForm(['password' => 'Sup3r-Secret-Passw0rd'])
        ->call('save')
        ->assertHasFormErrors(['passwordConfirmation' => 'required']);
});

it('generates a password that fills both fields and passes validation', function(): void {
    $component = Livewire::test(EditProfile::class)
        ->callAction(TestAction::make('generatePassword')->schemaComponent('password'))
        ->assertNotified();

    $password = $component->get('data.password');

    expect($password)->toBeString()->toHaveLength(16)
        ->and($component->get('data.passwordConfirmation'))->toBe($password);

    $component
        ->fillForm(['currentPassword' => 'Curr3nt-Passw0rd'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check($password, $this->client->refresh()->password))->toBeTrue();
});

it('does not store the password confirmation', function(): void {
    Livewire::test(EditProfile::class)
        ->fillForm([
            'password'             => 'Sup3r-Secret-Passw0rd',
            'passwordConfirmation' => 'Sup3r-Secret-Passw0rd',
            'currentPassword'      => 'Curr3nt-Passw0rd',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->client->refresh()->getAttributes())->not->toHaveKey('passwordConfirmation');
});
