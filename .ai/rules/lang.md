---
paths:
  - lang/es.json
---

# Lang

## Framework mail chrome lives in lang/es.json, minus the keys finisterre owns
Laravel's notification mail view (`Illuminate/Notifications/.../email.blade.php`) asks for its greeting, salutation, subcopy and footer by their English text — `@lang('Hello!')`, `@lang('Regards,')`. Those are JSON string keys, not `lang/es/*.php` groups, so they belong in `lang/es.json`. Without it a Spanish notification opens with "Hello!" and signs off "Regards,".

Trap: `arzcode/finisterre` registers its lang directory AFTER the app's in the loader's `paths`, so for any key its own `resources/lang/es.json` defines, the package wins over `lang/es.json`. Today that is only the "trouble clicking the button" subcopy — do not restate that key here, it would be dead weight. Check `vendor/arzcode/finisterre/resources/lang/es.json` before adding a key.

`tests/Feature/TranslationsTest.php` covers both: the chrome strings resolve in Spanish, and a rendered `CupidaCreditExhausted` mail contains no English greeting or salutation. The key-parity test only globs `lang/en/*.php`, so a JSON file needs no English twin.
