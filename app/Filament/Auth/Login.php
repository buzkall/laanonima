<?php

namespace App\Filament\Auth;

use App\Enums\UserRole;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use App\Support\BookRequestSignIn;
use Arzcode\FilamentMagicLogin\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
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
        return BookRequestSignIn::subheading(parent::getSubheading());
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
     * The password is not written down, only pointed at: the riddle under the field
     * is enough for anybody in the room and no use to anybody outside it. It goes below
     * the box rather than in the hint slot beside the label, where it is too long to sit.
     */
    protected function getPasswordFormComponent(): Component
    {
        $password = parent::getPasswordFormComponent();

        if ($password instanceof TextInput && $this->isDemoLogin()) {
            $password->helperText(__('auth.demo.password_hint'));
        }

        return $password;
    }

    /**
     * Only the client panel is dressed for the demo; the admin form stays blank.
     */
    protected function isDemoLogin(): bool
    {
        return Filament::getCurrentOrDefaultPanel()?->getId() === UserRole::Client->panelId();
    }
}
