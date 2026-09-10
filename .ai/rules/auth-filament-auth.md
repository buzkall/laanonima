---
paths:
  - 'app/Support/BookRequestSignIn.php,app/Filament/Auth/Login.php,app/Filament/Auth/Register.php'
---

# Auth Filament Auth

## The auth pages say why a guest is being asked to sign in
Asking for a book is the only thing on the shop behind `auth`, so a guest pressing "pídenoslo" lands on the client panel's login with no reason on it. `BookRequestSignIn::subheading()` prepends `auth.book_request.reason` to Filament's own subheading, and both `Login` and `Register` call it from `getSubheading()` — the register page too, because the way there is a link on the login form and `url.intended` survives the trip.

It reads the reason off `url.intended` and matches it against the two request-form routes with `Route::matches()`, never against a path spelled out in the class: renaming `/pedir-libro` must not quietly turn the explanation off. Reading `url.intended` rather than flashing is what makes it survive login → register.

The reason is wrapped in `<span style="display:block">`: Filament's line opens with a lowercase "o", which runs badly straight after a full stop, and the panel stylesheet is Filament's own build so a utility class is not guaranteed to exist.
