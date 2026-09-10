---
paths:
  - resources/views/components/site-header.blade.php
  - resources/views/components/share-button.blade.php
---

# Components

## The header account link is resolved from the role, never hardcoded
The user icon in `site-header.blade.php` points at `auth()->user()?->role->panelUrl() ?? UserRole::Client->loginUrl()`: a visitor is sent to the client panel's login, a signed-in user to the panel their own role owns. Both methods go through `UserRole::panelId()`, so adding a role or panel still means touching only that match (see `.ai/rules/app.md`). Never write `/client/login` or a `filament.*` route name into the view.

The icon is `<x-heroicon-o-user>` from blade-heroicons and inherits `--on-cover` through `currentColor`, so it recolours per book on `books/show`. It carries no visible label — `sr-only` text plus `title` hold the string (`books.public.login` / `books.public.account`), which keeps the header to the one `wide` breakpoint the frontend rules allow.

## The wordmark is the one thing in the header that does not recolour
The header used to set `config('app.name')` in Gloock; it now renders `resources/images/brand/la-anonima-logo.png` through `Vite::asset()`. That PNG is listed as its own entry in `vite.config.js` input purely so Vite hashes it and writes a manifest entry — nothing in the CSS or JS entries references it, so removing it from `input` silently breaks the header in production.

The logo carries its own brand colors (black `#000000`, green `#80d7ac`, magenta `#fa008a` on transparent), so unlike every other element in the bar it does NOT follow `--on-cover`. Two known consequences, accepted deliberately: the green "LA" sits on `#80d7ac` wherever `--cover` is the fallback (the home page, the shelf, any book with no cover color) and reads as a knockout; the black wordmark loses contrast on a dark cover. Swapping to a mono mask filled with `currentColor` is the fix if that ever stops being acceptable.

`config('app.name')` survives as the img `alt`, which keeps the accessible name unchanged. The `books.public.tagline` string is gone from the bar but the key stays — `books/index.blade.php` still uses it as the page `<title>`.

The bar is `items-center` now, not `items-baseline`: an image has no useful baseline to align the right-hand links against.

## The phone menu is a `<details>`, and its links are written once
Below `wide:` the two nav links collapse into a `<details>` hamburger sitting between the wordmark and the user icon; from `wide:` up the inline `<nav>` shows and the disclosure is hidden.

`<details>` rather than a script or Alpine: Alpine only arrives with Livewire, so it exists on La Cupida and nowhere else, and the bar is on every page. The browser owns the open state and the `aria-expanded` that goes with it. `[&::-webkit-details-marker]:hidden` + `list-none` kill the triangle; `group-open:` swaps bars-3 for x-mark.

The links live in one `$links` array at the top of the file and are rendered twice (inline nav, disclosure panel). Never maintain two hand-written lists — that is how a link lands on a laptop and nowhere else. `tests/Feature/Books/SiteHeaderTest.php` asserts each route appears exactly twice inside `<header>`.

The panel is `absolute inset-x-0 top-full z-50` off a `relative` header, never an element that grows the bar: `books/show.blade.php` measures the header into `--top-bar` on every paint, so a panel that made the header taller would shove the floating cover down the page each time it opened. `z-50` clears that cover (z-30).

## One share control, driven by data attributes and a delegated listener
`<x-share-button>` carries what it shares in `data-share-*`; the behaviour is `resources/js/share.js`, delegated from `document` and mounted unconditionally from `app.js`. That is what lets the same component sit inside La Cupida's result panel, which Livewire morphs on every round — a listener bound to the button itself would be replaced with it.

`navigator.share` first (called before any `await`, or the sheet loses the click's activation), then the clipboard, then a textarea + `execCommand`. The modern clipboard is *present and refusing* more often than it is absent — an unfocused document and a denied permission both reject — so try it and fall through on rejection rather than feature-detecting. The label only flashes "copied" when a copy actually succeeded.

No button is rendered without JavaScript, which is deliberate: there is nothing useful to degrade to.
