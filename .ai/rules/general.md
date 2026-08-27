---
paths:
  - '**'
---

# General

## All user-facing strings must be in Spanish
The app runs with APP_LOCALE=es. Never hardcode user-facing text: put it in a `lang/es/*.php` file and read it with `__('file.key')`. Add the same keys to `lang/en/` — `tests/Feature/TranslationsTest.php` fails if the two locales drift out of key parity.

Filament ships its own `es` translations, so its built-in UI (buttons, modals, notifications) is already Spanish. What is NOT translated automatically are labels Filament derives from column names, so every field, column, filter and section needs an explicit `->label(__('...'))`.

Careful with PHPStan: `__()` is typed `array|string|null`. Returning it from a ternary that also yields `null` breaks a `?string` return type — use an early-return closure instead of `? :`.
