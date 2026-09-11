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

Providers often return a record with no cover, so a cover is never guaranteed. `DownloadCoverAction` is the manual way in: it takes a URL, fetches it through the same guarded pipeline, and on an edit page attaches it immediately (calling `loadStateFromRelationships(true)` on the injected `$component` so the field redraws). Book form actions live in `app/Filament/Resources/Books/Actions/` as `Action` subclasses with `getDefaultName()`, which is the name tests pass to `callFormComponentAction()`.

## A stored cover color is never written over
`books.cover_color` is one column and there is no flag beside it: `SyncCoverColor` fills it while it is empty and returns early once it holds anything, so a color read off a cover and a color typed into the panel are the same thing and both survive every later upload and deletion. Emptying the field is how a bookseller asks for the cover to be read again; `ResetCoverColorAction` (the hint action on the `cover_color` field) reads the cover there and then. Deleting every image no longer clears the color, so a coverless book keeps the one it had.

`SyncCoverColor` must read the row, not the object (`$book->fresh('media')`). The instance a media event hands over is stale in both directions — its attributes predate the form's save, so a color just emptied still looks present — and it is gone entirely when the book itself is being deleted.

Reordering is deliberately not a trigger any more (see `AppServiceProvider::syncBookCoverColors`): it can no longer change a stored color, and `setNewOrder` raises one event per row, so a book whose color had been emptied would read the collection while two images still shared an `order_column`.

## A 502 with an empty log means an extension took a global helper

In September 2026 `/la-cupida` and every Filament save that reached a `defer()`
started answering 502 while the database write went through -- the record was
created, the response never came back. Nothing was in `storage/logs`, and the
FPM log only said `child NNN exited with code 255`.

The cause was the **Swoole extension** installed on the production PHP: with
`swoole.use_shortname` at its default it registers a global `defer()`, and
Laravel only declares its own `if (! function_exists('defer'))`
(`Foundation/helpers.php`). Laravel's helper was therefore never declared and
every `defer()` in the app and its vendors reached Swoole's coroutine defer,
which throws `Swoole\Error: API must be called in the coroutine` as an
*uncaught* fatal -- killing the worker before the response was flushed. It was
fixed by disabling the extension on the server; the app was left unchanged.

Two things worth keeping from it:

- **A 502 is never in `storage/logs`.** The worker is gone before Laravel's
  handler runs. `ini_set('error_log', ...)` at the top of `public/index.php`,
  above `vendor/autoload.php`, is what makes those fatals readable -- it was
  the only thing that found this, after the Laravel log, the nginx log and the
  FPM log had all come back empty.
- **Suspect a shadowed global function** whenever a plain Laravel helper fails
  on one machine and not another. `Illuminate\Support\defer()` (and the other
  namespaced functions) are import-and-call, so `use function` is the immune
  form if this ever needs guarding in code again.

## Demo mode blocks destructive abilities at the gate
`config('site.demo_mode')` (env `DEMO_MODE`, **on unless set to false**) refuses the abilities listed in `DemoMode::BLOCKED_ABILITIES` — delete, deleteAny, forceDelete, forceDeleteAny, withdraw — to everybody, administrators included.

It is enforced once, by a `Gate::before` hook in `AppServiceProvider::blockDestructiveAbilitiesInDemoMode()`, not per policy. That is deliberate: Author, Book and Publisher have no `delete()` in a policy to edit, and Filament's `get_authorization_response()` calls the gate's before callbacks directly when the policy method is missing, so the refusal reaches those resources too. A new resource is covered without opting in.

The hook returns plain `false` rather than a `Response::deny($message)`, because Filament hides an action whose refusal carries no message and only disables the ones that explain themselves — the demo shows no delete buttons at all.

`phpunit.xml` pins `DEMO_MODE=false` so the suite tests the shop as it works; `tests/Feature/DemoModeTest.php` turns it back on per test.
