<?php

namespace App\Filament\Auth;

use App\Enums\UserRole;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use App\Support\BookRequestSignIn;
use Arzcode\FilamentMagicLogin\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Accepts a sign-in from a user who belongs to another panel.
 *
 * Filament refuses credentials at a panel the user cannot access, which reads as
 * "these credentials do not match our records" — wrong and alarming for a client who
 * typed their real password into the admin form. Signing in at the wrong panel is not
 * a failed sign-in, so authentication proceeds and
 * {@see LoginResponse} sends them to the panel their role owns.
 *
 * Extends the magic-link login page so both panels keep their "email me a link"
 * button; the plugin leaves a custom page alone when it carries that trait.
 */
class Login extends BaseLogin
{
    /**
     * Says why a reader who never asked to sign in is being asked to.
     *
     * Only when they were turned away from the book request form; everybody
     * else gets Filament's own line. {@see BookRequestSignIn}
     */
    public function getSubheading(): string|Htmlable|null
    {
        if ($this->hasRegisterBeside()) {
            return BookRequestSignIn::subheading(null);
        }

        return BookRequestSignIn::subheading(parent::getSubheading());
    }

    /**
     * On the client panel the page carries no heading of its own: each column has one.
     */
    public function getHeading(): string|Htmlable|null
    {
        return $this->hasRegisterBeside() ? null : parent::getHeading();
    }

    public function getMaxWidth(): Width|string|null
    {
        return $this->hasRegisterBeside() ? Width::FiveExtraLarge : parent::getMaxWidth();
    }

    /**
     * A reader who has no account yet sees the sign-up form beside the sign-in one,
     * instead of a link to a second page. Each sits in its own card; the client theme
     * strips the page's own card around them (`.fi-auth-split`). The register column hides while a
     * multi-factor challenge is on screen, like the login form itself.
     */
    public function content(Schema $schema): Schema
    {
        if (! $this->hasRegisterBeside()) {
            return parent::content($schema);
        }

        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 2])
                    ->extraAttributes(['class' => 'fi-auth-split'])
                    ->schema([
                        Section::make(parent::getHeading())
                            ->schema([
                                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE),
                                $this->getFormContentComponent(),
                                $this->getMultiFactorChallengeFormContentComponent(),
                                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER),
                            ]),
                        Section::make(__('filament-panels::auth/pages/register.heading'))
                            ->schema([
                                Livewire::make(Register::class, ['isBesideLogin' => true]),
                            ])
                            ->visible(fn(): bool => blank($this->userUndertakingMultiFactorAuthentication)),
                    ]),
            ]);
    }

    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if (parent::isUserAllowedToAccessPanel($user)) {
            return true;
        }

        // Only a user with a panel of their own gets in. Asking `canAccessPanel()`
        // rather than assuming keeps any future condition it grows — a verified
        // email, a suspended account — decisive here too.
        return $user instanceof User
            && $user->canAccessPanel(Filament::getPanel($user->role->panelId()));
    }

    /**
     * The demo opens on the client panel's login form with the shop's own address
     * already typed in, so nobody has to be told what to write. The address is the
     * one `config('site.contact_email')` holds and `DatabaseSeeder` gives to Lorena's
     * account — an admin, which signs in here and is redirected by {@see LoginResponse}.
     */
    protected function getEmailFormComponent(): Component
    {
        $email = parent::getEmailFormComponent();

        if ($this->isDemoLogin()) {
            $email->default(config('site.contact_email'));
        }

        return $email;
    }

    /**
     * Only the client panel is dressed for the demo; the admin form stays blank.
     */
    protected function isDemoLogin(): bool
    {
        return $this->isClientPanel();
    }

    protected function hasRegisterBeside(): bool
    {
        return $this->isClientPanel() && Filament::hasRegistration();
    }

    protected function isClientPanel(): bool
    {
        return Filament::getCurrentOrDefaultPanel()?->getId() === UserRole::Client->panelId();
    }
}
