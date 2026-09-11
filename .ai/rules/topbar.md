---
paths:
  - 'app/Providers/Filament/**,resources/views/filament/topbar/**'
---

# Topbar

## The way back to the shop hangs off USER_MENU_BEFORE
Both panels render `resources/views/filament/topbar/shop-link.blade.php` — an icon-only link to `route('home')` — through `PanelsRenderHook::USER_MENU_BEFORE`.

That hook, not `TOPBAR_END`: `TOPBAR_END` renders *outside* the `.fi-topbar-end` flex box in `filament/filament/resources/views/livewire/topbar.blade.php`, so anything put there drops out of the right-hand cluster. `USER_MENU_BEFORE` lands inside it, between the notifications bell and the avatar.

It only renders for a signed-in user whose user menu is in the topbar, which is exactly when a way back to the shop is wanted. The label lives in `lang/{es,en}/panel.php` under `topbar.shop`.
