<?php

use App\Enums\UserRole;
use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Register;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function(): void {
    Filament::setCurrentPanel('client');
});

it('signs a new reader up as a client', function(): void {
    livewire(Register::class)
        ->fillForm([
            'name'                 => 'Marta Ruiz',
            'email'                => 'marta@example.com',
            'phone'                => '600 100 200',
            'password'             => 'Contrasena123',
            'passwordConfirmation' => 'Contrasena123',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    assertDatabaseHas(User::class, [
        'email' => 'marta@example.com',
        'phone' => '600 100 200',
        'role'  => UserRole::Client->value,
    ]);

    expect(auth()->user())->toBeInstanceOf(User::class)
        ->and(auth()->user()->role)->toBe(UserRole::Client);
});

it('sends a reader who has just signed up to the client panel, not to a remembered admin URL', function(): void {
    session()->put('url.intended', url('/admin'));

    livewire(Register::class)
        ->fillForm([
            'name'                 => 'Marta Ruiz',
            'email'                => 'marta@example.com',
            'password'             => 'Contrasena123',
            'passwordConfirmation' => 'Contrasena123',
        ])
        ->call('register')
        ->assertRedirect(Filament::getPanel('client')->getUrl());
});

it('offers no registration on the admin panel', function(): void {
    expect(Filament::getPanel('admin')->hasRegistration())->toBeFalse();

    $this->get('/admin/register')->assertNotFound();
});

it('lets a reader correct their own telephone from the profile page', function(): void {
    $client = User::factory()->create(['phone' => null]);

    $this->actingAs($client)
        ->get('/client/profile')
        ->assertOk();

    livewire(EditProfile::class)
        ->fillForm(['phone' => '600 100 200'])
        ->call('save')
        ->assertHasNoFormErrors();

    assertDatabaseHas(User::class, [
        'id'    => $client->id,
        'phone' => '600 100 200',
    ]);
});

it('keeps the role out of the profile form', function(): void {
    $this->actingAs(User::factory()->create());

    expect(livewire(EditProfile::class)->instance()->form->getComponent('data.role'))->toBeNull();
});
