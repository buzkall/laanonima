---
paths:
  - 'app/**'
---

# App

## One role per user, one panel per role
`users.role` is a single string column cast to `App\Enums\UserRole` (Admin, Client) — there is no roles table and no multi-role support. The column defaults to `client`, so anything that creates a user without an explicit role gets a client.

Each role owns exactly one panel, mapped by `UserRole::panelId()` (Admin → `admin`, Client → `client`). `User::canAccessPanel()` compares the panel's id against that mapping, so access is mutually exclusive: admins get a 403 on the client panel just as clients do on the admin panel. Adding a role or a panel means updating `panelId()` — nowhere else. Because the `FilamentUser` contract is implemented, this applies in local too; Filament's "all users can access panels locally" default no longer holds. New admins must be created explicitly (`User::factory()->admin()`).

`UserPolicy` still allows any authenticated user to manage users; the panel gate is what keeps clients out of `UserResource`. Tighten the policy too if user management ever moves outside the admin panel.

## Book and publisher images live in the media library
Books and publishers have no image columns. A book keeps every picture in one ordered `Book::COVERS_COLLECTION` ('covers'); the first by `order_column` is the cover, which is what `cover()` / `coverUrl()` and the listing return. A publisher has a single-file `Publisher::LOGO_COLLECTION` ('logo').

Always pass `->disk(config('media-library.disk_name'))` to `SpatieMediaLibraryFileUpload`. Without it Filament uploads to `config('filament.default_filesystem_disk')` (FILESYSTEM_DISK, `local` in testing) while everything added in PHP goes to MEDIA_DISK (`public`), so uploads and downloaded covers end up on different disks and the second set silently 404s.

Conversions are `->nonQueued()`: there is no worker in front of the panel. Chain `nonQueued()` before `fit()` — `fit()` returns an ImageDriver, so the reverse order fails PHPStan.

`DownloadBookCover` returns JPEG bytes, it does not store anything. The ISBN lookup runs in a form with no record on create, so it only writes `cover_source_url`; `AttachBookCover` fetches after the save (CreateBook::afterCreate, and EditBook::afterSave only when `wasChanged('cover_source_url')`, so a deleted image is not resurrected). It checks for existing images with a relation query, not `hasMedia()`, because the record's media relation is still stale at that point.
