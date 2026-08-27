<?php

use App\Filament\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * Signs in through a panel's real login form, so the redirect under test is the one
 * the browser would follow.
 */
function signIn(string $panelId, User $user): Testable
{
    Filament::setCurrentPanel($panelId);

    return Livewire::test(Login::class)
        ->fillForm([
            'email'    => $user->email,
            'password' => 'password',
        ])
        ->call('authenticate');
}

it('sends a user to their own panel after signing in', function(string $panelId, string $role, string $destination): void {
    $user = User::factory()->{$role}()->create();

    signIn($panelId, $user)->assertRedirect($destination);
})->with([
    'client at the client panel' => ['client', 'client', '/client'],
    'admin at the admin panel'   => ['admin', 'admin', '/admin'],
]);

it('signs a user in at the other panel and sends them to their own', function(string $panelId, string $role, string $destination): void {
    $user = User::factory()->{$role}()->create();

    signIn($panelId, $user)
        ->assertHasNoFormErrors()
        ->assertRedirect($destination);

    expect(auth()->id())->toBe($user->getKey());
})->with([
    'client at the admin panel' => ['admin', 'client', '/client'],
    'admin at the client panel' => ['client', 'admin', '/admin'],
]);

it('still refuses credentials that do not match', function(): void {
    $user = User::factory()->create();

    Filament::setCurrentPanel('admin');

    Livewire::test(Login::class)
        ->fillForm([
            'email'    => $user->email,
            'password' => 'the-wrong-password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth()->check())->toBeFalse();
});

it('ignores an intended URL belonging to a panel the user cannot reach', function(): void {
    $client = User::factory()->create();

    session(['url.intended' => url('/admin/users')]);

    signIn('client', $client)->assertRedirect('/client');
});

it('keeps an intended URL inside the user own panel', function(): void {
    $admin = User::factory()->admin()->create();

    session(['url.intended' => url('/admin/users')]);

    signIn('admin', $admin)->assertRedirect(url('/admin/users'));
});

it('forgets an intended URL from another panel when visiting a panel', function(): void {
    session(['url.intended' => url('/admin/users')]);

    get('/client/login')->assertOk();

    expect(session('url.intended'))->toBeNull();
});

it('keeps an intended URL from the panel being visited', function(): void {
    get('/admin/users')->assertRedirect('/admin/login');

    expect(session('url.intended'))->toBe(url('/admin/users'));

    get('/admin/login')->assertOk();

    expect(session('url.intended'))->toBe(url('/admin/users'));
});

it('keeps the magic link button on both login pages', function(string $panelId): void {
    Filament::setCurrentPanel($panelId);

    Livewire::test(Login::class)->assertActionExists('magicLink');
})->with(['admin', 'client']);

it('renders both login pages', function(string $url): void {
    get($url)->assertOk();
})->with(['/admin/login', '/client/login']);
