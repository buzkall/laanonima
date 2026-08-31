---
paths:
  - resources/views/components/site-header.blade.php
---

# Components

## The header account link is resolved from the role, never hardcoded
The user icon in `site-header.blade.php` points at `auth()->user()?->role->panelUrl() ?? UserRole::Client->loginUrl()`: a visitor is sent to the client panel's login, a signed-in user to the panel their own role owns. Both methods go through `UserRole::panelId()`, so adding a role or panel still means touching only that match (see `.ai/rules/app.md`). Never write `/client/login` or a `filament.*` route name into the view.

The icon is `<x-heroicon-o-user>` from blade-heroicons and inherits `--on-cover` through `currentColor`, so it recolours per book on `books/show`. It carries no visible label — `sr-only` text plus `title` hold the string (`books.public.login` / `books.public.account`), which keeps the header to the one `wide` breakpoint the frontend rules allow.
