<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Support\PanelUrl;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

/**
 * Sends a user to their own panel after signing in, whichever panel's login form
 * they used.
 *
 * Filament's own response redirects to the intended URL, falling back to the panel
 * being looked at. Both are wrong here: a client signing in at `/client/login` with
 * `/admin` still remembered from an earlier visit would be sent somewhere their role
 * cannot go, and land on a 403 instead of their dashboard.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return redirect()->intended(Filament::getUrl());
        }

        $panel = Filament::getPanel($user->role->panelId());

        $intended = session()->pull('url.intended');

        return redirect(PanelUrl::belongsTo($intended, $panel)
            ? (string)$intended
            : ($panel->getUrl() ?? '/'));
    }
}
