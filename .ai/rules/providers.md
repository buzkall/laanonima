---
paths:
  - 'app/Providers/**'
---

# Providers

## Panels share one session, so intended URLs leak between them
The admin and client panels run on one session, so `url.intended` set by a guest turned away from one panel survives into the other and hijacks every `redirect()->intended()` — that is how a client signing in at `/client/login` ended up on `/admin` with a 403.

Two guards, both needed: `App\Http\Middleware\ForgetIntendedUrlFromOtherPanels` (in both panels' `middleware()`) drops an intended URL belonging to another panel, covering the magic-link consume route and anything else on panel routes; `App\Http\Responses\LoginResponse` (bound in AppServiceProvider) sends a user to the panel their role owns and honours an intended URL only when it lives inside it. The middleware alone is not enough — the Livewire request that submits the login form goes to `/livewire/update`, outside the panel middleware group.

Note Filament refuses a sign-in at a panel `canAccessPanel()` denies: it is a credentials validation error, not a redirect.
