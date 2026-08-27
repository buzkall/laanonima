---
paths:
  - 'app/Filament/Auth/**'
---

# Auth

## Signing in at the wrong panel is allowed, then redirected
Both panels use `App\Filament\Auth\Login`, which overrides `isUserAllowedToAccessPanel()` so valid credentials are accepted even at a panel the user's role cannot enter. Filament's default refuses them with "these credentials do not match our records", which is wrong and alarming for a client typing their real password into the admin form. Authentication proceeds and `App\Http\Responses\LoginResponse` sends them to the panel their role owns. A wrong password is still refused.

The page extends `Arzcode\FilamentMagicLogin\Pages\Login`, not Filament's — the magic-login plugin throws when a panel's login page does not carry `HasMagicLinkAction`, and extending its page keeps the "email me a link" button on both panels.
