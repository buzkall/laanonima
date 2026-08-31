---
paths:
  - 'lang/vendor/**'
---

# Vendor

## Filament's Spanish is de-usted-ed with partial overrides
Filament ships an `es` locale that addresses the reader as "usted" ("Entre a su cuenta"), and inconsistently so — some of its own files already use "tú". The shop speaks to readers as "tú" everywhere, so `lang/vendor/<namespace>/es/<group>.php` overrides only the offending keys.

They are PARTIAL on purpose: Laravel merges a vendor override with `array_replace_recursive`, so every key not listed keeps tracking upstream and arrives translated after a package upgrade. Never publish the whole locale with `vendor:publish` — that freezes hundreds of strings.

The file path IS the address: `lang/vendor/filament-panels/es/auth/pages/login.php` overrides `filament-panels::auth/pages/login`. Nested groups work.

Two tests in `tests/Feature/TranslationsTest.php` keep it from rotting: every overridden key must still exist in the package's own file (a rename upstream would otherwise silently stop overriding), and no override value may match the `FORMAL_ADDRESS` regex. That regex is deliberately narrow — "antes de que se verifique" is impersonal subjunctive, not formal address.

Multi-factor auth, export/import, the query builder and soft-delete actions are NOT overridden: this app has none of them. Turning one on means covering its strings too.
