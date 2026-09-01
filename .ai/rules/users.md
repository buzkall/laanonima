---
paths:
  - 'app/Filament/Resources/Users/**'
---

# Users

## Role tabs replace the role filter, and requests hang off client accounts
`ListUsers::getTabs()` builds one tab per `UserRole` from `ListUsers::TAB_ORDER`, which puts Client before Admin on purpose: readers are the list anyone opens this page for, Filament opens the first tab, so clients are what shows before a click. Do not restore the `role` SelectFilter to `UsersTable` — inside a tab it can only ever narrow to nothing, which is why it was removed. Tab labels are `user.tabs.{role}` (plural), not the singular `user.roles.{role}` the badge column uses.

`BookRequestsRelationManager` lists a reader's requests on `EditUser` (there is no view page). It is a listing only: `canViewForRecord()` hides it from administrator accounts, and its `EditAction` carries a `->url()` to `BookRequestResource` rather than opening a modal, because the bookseller needs the whole form — status, internal notes, catalogue link — not half of it. `getBadge()` counts open requests, mirroring the resource's sidebar badge, and must guard `instanceof User` since the parent signature types `$ownerRecord` as `Model`.
