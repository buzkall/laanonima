---
paths:
  - 'app/**'
---

# App

## One role per user, admins only in Filament
`users.role` is a single string column cast to `App\Enums\UserRole` (Admin, Client) — there is no roles table and no multi-role support. The column defaults to `client`, so anything that creates a user without an explicit role gets a client.

`User::canAccessPanel()` (FilamentUser contract) returns true only for `UserRole::Admin`, so clients get a 403 on every panel route. Because the contract is implemented, this applies in local too — Filament's "all users can access panels locally" default no longer holds. New admins must be created explicitly (`User::factory()->admin()`).

`UserPolicy` still allows any authenticated user to manage users; the panel gate is what keeps clients out. Tighten the policy too if user management ever moves outside Filament.
