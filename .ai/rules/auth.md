---
paths:
  - 'app/Filament/Auth/**'
---

# Auth

## Signing in at the wrong panel is allowed, then redirected
Both panels use `App\Filament\Auth\Login`, which overrides `isUserAllowedToAccessPanel()` so valid credentials are accepted even at a panel the user's role cannot enter. Filament's default refuses them with "these credentials do not match our records", which is wrong and alarming for a client typing their real password into the admin form. Authentication proceeds and `App\Http\Responses\LoginResponse` sends them to the panel their role owns. A wrong password is still refused.

The page extends `Arzcode\FilamentMagicLogin\Pages\Login`, not Filament's — the magic-login plugin throws when a panel's login page does not carry `HasMagicLinkAction`, and extending its page keeps the "email me a link" button on both panels.

## Only the client panel registers, and only as a client
`ClientPanelProvider` enables `->registration(Register::class)`; the admin panel does not. An administrator is made by an administrator, in the admin panel's users resource.

Both panels register `->profile(EditProfile::class, isSimple: false)`. `isSimple: false` is the point of that call: Filament's default renders the profile on the bare login-page layout, with no sidebar, no topbar and no way back other than the browser, which is not where you want somebody who came in to change their telephone. Keep the argument if you touch either provider.

`EditProfile::form()` puts the fields in the users resource's `user.sections.account` section at `columns(2)`, and `defaultForm()` turns off the inline labels Filament switches on for a non-simple page -- two columns of inline labels leave the inputs too narrow. The password field carries `columnStart(1)` so it opens a row and the two password boxes pair up instead of one of them landing beside the telephone; anything added above them has to keep that row even.

`App\Filament\Auth\Register::mutateFormDataBeforeRegister()` writes `UserRole::Client` rather than trusting the `users.role` column default, and no role field is ever put on the register or profile form — the role decides which panel its owner may enter.

Registration signs the new user straight in, so it hits the shared-session intended-URL trap: `App\Http\Responses\RegistrationResponse` extends `LoginResponse` and is bound in `AppServiceProvider` so signing up follows the same redirect rule as signing in.

Both pages carry a `phone` field (`__('user.fields.phone')`): the shop reads the number off `users` when calling a reader back about a request, so the profile is where a mistyped one is corrected.
