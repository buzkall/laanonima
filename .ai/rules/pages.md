---
paths:
  - 'app/Filament/Pages/CorreosPlayground.php,config/correos.php,config/laravel-correos.php'
---

# Pages

## The Correos page checks its payload before building the DTO
`DeliveryRequestData` types the sender and addressee address, locality, province, cp and country as plain strings, and the page strips empties before hydrating (optional properties are `string|Optional`, so a null is a TypeError). A missing required one therefore surfaces as "the constructor requires 25 parameters, 21 given" from inside spatie/laravel-data, not as an answer from Correos — which is why `CorreosPlayground::DELIVERY_FIELDS` is checked first and the call is refused with a notification.

Contract numbers and the sender live in `config/correos.php`, not in the published `config/laravel-correos.php`: the SDK never reads them (they travel in the payload), and a re-publish of the package config would wipe them.

The page gates on `! app()->isProduction()` rather than `isLocal()` so the test suite, which runs under "testing", can still reach it. It creates real shipments — pre-production books real preregistrations — so the three writes confirm first.
