---
paths:
  - app/Filament/Resources/Users/Schemas/UserForm.php
---

# Schemas

## The account form's conditional confirmation and read-only verification
`password_confirmation` is always on the page; only `->required(fn (Get $get) => filled($get('password')))` is conditional. It was hidden until a password was typed — first by a `visible()` closure, then by `visibleJs()` — and neither was worth it: a box that appears under the cursor moves the rest of the form while somebody is tabbing through it. Do not put the condition back on visibility; the server's word on whether a confirmation was owed is `required()`, which is also why `fillForm()` in tests keeps working on the field.

`email_verified_at` is a read-only `TextEntry` badge in the section's `afterHeader()`, not a `DateTimePicker`: verification is something the reader does by following our link, not something an administrator types. Nothing in the panel can set or clear it any more — add an action if that is ever wanted, do not put the picker back. Its text comes from `user.badges.verified` / `user.placeholders.not_verified`.

Field order in the section is name, email, phone, role, password, confirmation, so the two password boxes land side by side in the 2-column grid.
