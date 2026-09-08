---
paths:
  - 'app/Support/AccountPassword.php,app/Filament/Actions/GeneratePasswordAction.php,app/Filament/Resources/Users/Schemas/UserForm.php,app/Filament/Auth/EditProfile.php'
---

# Filament Auth

## One password policy for both forms that set one
Two forms set a password: `UserForm` (an administrator setting somebody else's) and `App\Filament\Auth\EditProfile` (a reader setting their own). A reader must be able to type back a password the panel generated for them, so the rule and the generator live in `App\Support\AccountPassword` (`rule()`, `generate()`, `MINIMUM_LENGTH`, `GENERATED_LENGTH`) and both read them from there — never inline `Password::min(...)` or `str()->password()` in a form again.

The "Generar contraseña" hint action is `App\Filament\Actions\GeneratePasswordAction`. It fills both boxes, then dispatches `reveal-password` and `copy-to-clipboard`; those only do anything if the password field also carries `GeneratePasswordAction::revealAndCopyAttributes()` in `extraAlpineAttributes()` (the Alpine `isPasswordRevealed` var exists only on a `revealable()` field). The confirmation box is named `password_confirmation` in the resource and `passwordConfirmation` on the profile page, hence `->confirmationField()`.

`EditProfile` keeps Filament's `dehydrated(filled)` + `dehydrateStateUsing(Hash::make)` rather than `saved()` and the `hashed` cast: the base page writes the saved value into the session's `password_hash_*`, so an unhashed one there signs the reader out on the next request. Its confirmation box uses `dehydrated(false)`, not `saved(false)` — `handleRecordUpdate()` passes the form state straight to `update()`.
