---
paths:
  - resources/views/components/site-header.blade.php
---

# Components

## The header account link is resolved from the role, never hardcoded
The user icon in `site-header.blade.php` points at `auth()->user()?->role->panelUrl() ?? UserRole::Client->loginUrl()`: a visitor is sent to the client panel's login, a signed-in user to the panel their own role owns. Both methods go through `UserRole::panelId()`, so adding a role or panel still means touching only that match (see `.ai/rules/app.md`). Never write `/client/login` or a `filament.*` route name into the view.

The icon is `<x-heroicon-o-user>` from blade-heroicons and inherits `--on-cover` through `currentColor`, so it recolours per book on `books/show`. It carries no visible label — `sr-only` text plus `title` hold the string (`books.public.login` / `books.public.account`), which keeps the header to the one `wide` breakpoint the frontend rules allow.

## The wordmark is the one thing in the header that does not recolour
The header used to set `config('app.name')` in Gloock; it now renders `resources/images/brand/la-anonima-logo.png` through `Vite::asset()`. That PNG is listed as its own entry in `vite.config.js` input purely so Vite hashes it and writes a manifest entry — nothing in the CSS or JS entries references it, so removing it from `input` silently breaks the header in production.

The logo carries its own brand colours (black `#000000`, green `#80d7ac`, magenta `#fa008a` on transparent), so unlike every other element in the bar it does NOT follow `--on-cover`. Two known consequences, accepted deliberately: the green "LA" sits on `#80d7ac` wherever `--cover` is the fallback (the home page, the shelf, any book with no cover colour) and reads as a knockout; the black wordmark loses contrast on a dark cover. Swapping to a mono mask filled with `currentColor` is the fix if that ever stops being acceptable.

`config('app.name')` survives as the img `alt`, which keeps the accessible name unchanged. The `books.public.tagline` string is gone from the bar but the key stays — `books/index.blade.php` still uses it as the page `<title>`.

The bar is `items-center` now, not `items-baseline`: an image has no useful baseline to align the right-hand links against.
