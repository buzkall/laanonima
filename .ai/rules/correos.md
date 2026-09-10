---
paths:
  - 'app/Support/Correos/**'
---

# Correos

## Offline Correos mode fakes the network, not the SDK
`CORREOS_FAKE=true` makes `FakeCorreos` answer the SDK from memory, because Correos has no sandbox to sign up for: PRE credentials come from a commercial contact and only answer from a whitelisted IPv4 on weekdays.

It attaches a Saloon `MockClient` to the three connectors instead of swapping `CorreosShipping`, so the payload is still built and serialized, responses still hydrate into the real DTOs, and a 200 carrying an `error` still becomes a `CorreosApiException` — a fake that returned DTOs directly would test none of that.

It also rebinds the authenticator (`FakeCorreosAuthenticator`): Saloon builds the pending request in full before the mock sender answers, so the real one would still go out to CorreosID for a token — and fail, since there are no credentials. `AppServiceProvider` refuses to install any of it in production.
