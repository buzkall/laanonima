---
paths:
  - '**'
---

# General

## All user-facing strings must be in Spanish
The app runs with APP_LOCALE=es. Never hardcode user-facing text: put it in a `lang/es/*.php` file and read it with `__('file.key')`. Add the same keys to `lang/en/` — `tests/Feature/TranslationsTest.php` fails if the two locales drift out of key parity.

Filament ships its own `es` translations, so its built-in UI (buttons, modals, notifications) is already Spanish. What is NOT translated automatically are labels Filament derives from column names, so every field, column, filter and section needs an explicit `->label(__('...'))`.

Careful with PHPStan: `__()` is typed `array|string|null`. Returning it from a ternary that also yields `null` breaks a `?string` return type — use an early-return closure instead of `? :`.

## Always run Pint after Rector
Rector rewrites code without regard for `pint.json`, so its output can violate this project's style (closure spacing, `=>` alignment, cast spacing). Never run `vendor/bin/rector process` on its own — use `composer rector`, which chains `rector process` then `pint --dirty`. `rector process` exits 0 even when it changes files, so the Pint step always runs.

`composer rector:check` (dry-run, no Pint) is the CI gate and runs in `ci:check` after `lint:check`.

Deliberate skips in `rector.php`:

- `SafeDeclareStrictTypesRector` — no file declares `strict_types` today, and enabling it across `config/` changes scalar coercion at runtime rather than at test time. Remove only as a project-wide decision.
- `AddOverrideAttributeToOverriddenMethodsRector` and `AddOverrideAttributeToOverriddenPropertiesRector` — no `#[Override]` anywhere. Laravel/Filament/Livewire are override-heavy by design, so the attribute lands on a large share of methods for no reader benefit and turns a parent-side rename during a package upgrade into a fatal error. The properties rule is a PHP 8.5 rule and dormant at the current target; it is skipped so it stays off if the PHP constraint is bumped.
- `ReadOnlyPropertyRector` — Livewire/Filament hydrate public properties by reflection.
- `ClassPropertyAssignToConstructorPromotionRector` under `app/Filament` — static props carry union types like `string|BackedEnum|null` that promotion breaks.

`withPhpSets()` takes its target from `composer.json`, which is `php: ^8.3` — so PHP 8.4+ rules (e.g. parenthesisless `new`) are NOT applied. Bump the constraint if the project actually requires 8.4+.

## `composer ci:check` runs before every push

`.githooks/pre-push` runs the full `composer ci:check` (pint, rector, phpstan, tests) and aborts the push if any step fails, so a Rector or Pint violation can never reach GitHub Actions again. It is enabled per clone with `git config core.hooksPath .githooks`, which `composer setup` does for you.

`git push --no-verify` skips it — for genuine emergencies only. When the hook fails on Rector, `composer rector` fixes it and re-runs Pint.
