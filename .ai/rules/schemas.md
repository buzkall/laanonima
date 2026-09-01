---
paths:
  - app/Filament/Resources/Users/Schemas/UserForm.php
---

# Schemas

## The account form's conditional confirmation and read-only verification
`password_confirmation` is revealed by `visibleJs()`, not a `visible()` closure. A PHP condition would only re-evaluate when the password field blurs, by which point Tab has already carried focus past the spot the box appears in; `visibleJs` puts it in the DOM on the first keystroke with no round trip. It is presentation only — the server's word is still `->required(fn (Get $get) => filled($get('password')))`, which is also why `fillForm()` in tests keeps working on the field.

`email_verified_at` is a read-only `TextEntry` badge in the section's `afterHeader()`, not a `DateTimePicker`: verification is something the reader does by following our link, not something an administrator types. Nothing in the panel can set or clear it any more — add an action if that is ever wanted, do not put the picker back. Its text comes from `user.badges.verified` / `user.placeholders.not_verified`.

Field order in the section is name, email, phone, role, password, confirmation, so the two password boxes land side by side in the 2-column grid.
