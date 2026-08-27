---
paths:
  - 'app/**'
---

# App

## One role per user, one panel per role
`users.role` is a single string column cast to `App\Enums\UserRole` (Admin, Client) — there is no roles table and no multi-role support. The column defaults to `client`, so anything that creates a user without an explicit role gets a client.

Each role owns exactly one panel, mapped by `UserRole::panelId()` (Admin → `admin`, Client → `client`). `User::canAccessPanel()` compares the panel's id against that mapping, so access is mutually exclusive: admins get a 403 on the client panel just as clients do on the admin panel. Adding a role or a panel means updating `panelId()` — nowhere else. Because the `FilamentUser` contract is implemented, this applies in local too; Filament's "all users can access panels locally" default no longer holds. New admins must be created explicitly (`User::factory()->admin()`).

`UserPolicy` still allows any authenticated user to manage users; the panel gate is what keeps clients out of `UserResource`. Tighten the policy too if user management ever moves outside the admin panel.
