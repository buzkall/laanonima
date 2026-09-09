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

## Both panels wear the wordmark, in two colorways
`brandLogo()` / `darkModeBrandLogo()` in `AdminPanelProvider` and `ClientPanelProvider` render `resources/images/brand/la-anonima-logo.png` and its `-dark` sibling at `brandLogoHeight('2rem')`, replacing Filament's text logo. Both are passed as closures on purpose: `panel()` runs while the application boots, and resolving `Vite::asset()` eagerly there would read the manifest before it is guaranteed to be readable.

The dark file is the same wordmark with the black glyphs turned white and the brand green and magenta left alone — the black wordmark disappears against Filament's dark shell, and a CSS `invert` would wreck the two brand colors. Regenerate it from the light PNG rather than editing it by hand:

```
magick resources/images/brand/la-anonima-logo.png -alpha extract /tmp/alpha.png
magick resources/images/brand/la-anonima-logo.png -alpha off -fuzz 2% -fill white -opaque black /tmp/rgb.png
magick /tmp/rgb.png /tmp/alpha.png -alpha off -colorspace sRGB -compose CopyOpacity -composite resources/images/brand/la-anonima-logo-dark.png
```

Both files are listed in `vite.config.js` input so the manifest carries them (see `.ai/rules/components.md`); adding the dark one means `npm run build` before the panels can render it.

`favicon(asset('favicon.svg'))` puts the shop's own mark in the panel tabs in place of Filament's. The public layouts pair `favicon.ico` with `favicon.svg`, but Filament's base layout renders a single `rel="icon"` with no `type`, so the panels get the SVG alone and the browser sniffs it from the served MIME type.
