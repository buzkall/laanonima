<?php

namespace App\Filament\Auth;

use App\Http\Responses\LoginResponse;
use App\Models\User;
use Arzcode\FilamentMagicLogin\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;

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
}
