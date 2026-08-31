---
paths:
  - 'app/Providers/**'
---

# Providers

## Panels share one session, so intended URLs leak between them
The admin and client panels run on one session, so `url.intended` set by a guest turned away from one panel survives into the other and hijacks every `redirect()->intended()` — that is how a client signing in at `/client/login` ended up on `/admin` with a 403.

Two guards, both needed: `App\Http\Middleware\ForgetIntendedUrlFromOtherPanels` (in both panels' `middleware()`) drops an intended URL belonging to another panel, covering the magic-link consume route and anything else on panel routes; `App\Http\Responses\LoginResponse` (bound in AppServiceProvider) sends a user to the panel their role owns and honours an intended URL when it lives inside it. The middleware alone is not enough — the Livewire request that submits the login form goes to `/livewire/update`, outside the panel middleware group.

A URL belonging to no panel at all (`PanelUrl::isPublic()`) is a page of the shop, open to every role: both guards keep it and the login response follows it. That is what carries a reader sent to sign in from `/pedir-libro` back to the form. Only another panel's URL is dangerous, and only that is dropped.

Note Filament refuses a sign-in at a panel `canAccessPanel()` denies: it is a credentials validation error, not a redirect.
