# Upgrading shuvroroy/filament-spatie-laravel-backup from v3 to v4

These are the steps followed in one project, written so they can be repeated in other projects.
Source: https://github.com/shuvroroy/filament-spatie-laravel-backup/blob/main/UPGRADE.md

## 1. Check the versions it supports

v4 needs PHP 8.2–8.5, Laravel 12–13, Filament 4–5 and spatie/laravel-backup 9–10.
PHP 8.2 only works with Laravel 12 and spatie/laravel-backup 9.

```sh
php -v
composer show laravel/framework filament/filament spatie/laravel-backup | grep -E "^(name|versions)"
composer why shuvroroy/filament-spatie-laravel-backup   # is it in require or require-dev?
```

If any of these are outside the range, upgrade them first.

## 2. Find the v3 APIs that v4 removes, before upgrading

```sh
grep -rnE "usingPolingInterval|getPolingInterval|Option::|ONLY_DB|clearCachedBackupDestinationData|FilamentSpatieLaravelBackup::getDisk|detectBackupType|Backups::create\(|function getActions\(" app config tests resources
grep -rn "FilamentSpatieLaravelBackupPlugin" app/Providers
```

Replace any matches:

| v3 | v4 |
| --- | --- |
| `usingPolingInterval()` / `getPolingInterval()` | `usingPollingInterval()` / `getPollingInterval()` (may now return `null`) |
| `Option::ONLY_DB` | `BackupType::ONLY_DATABASE` |
| `Option::ONLY_FILES` | `BackupType::ONLY_FILES` |
| `Option::ALL` / `Backups::create('')` | `BackupType::DATABASE_AND_FILES` / `'db-and-files'` |
| `detectBackupType()` returning a string | now returns a `BackupType` enum, so use `->value` |
| `FilamentSpatieLaravelBackup::getDisk()` | removed: read the disk from your component state, or use `getDisks()` |
| `clearCachedBackupDestinationData($disk, $name)` | `clearBackupDestinationCache($disk, $name)` (both arguments required) |
| `clearCachedBackupDestinationData()` | `clearBackupDestinationCaches()` |
| `getActions()` override on a custom `Backups` page | `getHeaderActions()` |

The enum's import is `ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType`.

## 3. Upgrade the package

Keep the section it was already in. The guide's command leaves out `--dev`, and without it a dev-only install moves to `require`.

```sh
# in require-dev
composer require --dev "shuvroroy/filament-spatie-laravel-backup:^4.0" --with-all-dependencies --no-interaction
# in require
composer require "shuvroroy/filament-spatie-laravel-backup:^4.0" --with-all-dependencies --no-interaction
```

Check that only the packages you expected changed:

```sh
git diff composer.lock | grep -E '^[-+]\s+"version"'
```

If the project's `post-update-cmd` doesn't run `filament:upgrade` / `filament:assets`, run `php artisan filament:assets` to republish the plugin CSS.

## 4. Publish Spatie's config, or compare it

```sh
ls config/backup.php || php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag="backup-config"
```

- **Not published before:** publish it now (the guide requires this).
- **Already published:** diff it against `../vendor/spatie/laravel-backup/config/backup.php`. The monitor definitions under `monitor_backups` must use the `health_checks` key.
- **Point the notifications somewhere real.** The published config mails every backup event (failed, successful, unhealthy, cleanup) to `'to' => 'your@example.com'`. Read the address from `.env` instead, with a fallback so an empty variable doesn't send mail to nobody:
  ```php
  'to' => env('ADMIN_EMAIL') ?: env('SITE_CONTACT_EMAIL', 'hola@laanonimalibreria.com'),
  ```
  Use `env()` here, not `config('site.admin_email')`. One config file can't reliably read another while the config is being loaded. Document the variable in `.env.example`, and check that `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` are real too, because the `from` block reads them.
- Run `vendor/bin/pint --dirty --format agent` afterwards. The published file doesn't follow the project's Pint rules (for example `binary_operator_spaces`).

## 5. Review the behavior changes

- **Polling** changed from 4s to 30s. To keep the old interval, use `->usingPollingInterval('4s')`. `null` turns polling off.
- **Metadata cache** changed from 4s to 30s. Use `->cacheDuration(4)` to keep the old duration, or `->cacheDuration(0)` to disable it.
- **Listing** is now newest first, 10 per page (10/25/50). The disk filter now only queries the selected disk.
- **Backup type** is worked out only from the `only-db-` / `only-files-` filename prefixes. Anything else counts as database + files.
- **Record IDs** from `getBackupDestinationStatusData()` are now deterministic SHA-1 strings. Update any tests that assert on numeric IDs or on the old ordering.

## 6. Queue: the most important check for production

In v4, backup jobs are dispatched **right away** instead of after the response. They now **throw on a non-zero exit code**.

- With `QUEUE_CONNECTION=sync`, the backup runs inside the Livewire request and can hit the nginx/FPM/proxy timeout.
- Before enabling manual backups, point the plugin at an async connection that has a running worker:

```php
FilamentSpatieLaravelBackupPlugin::make()
    ->usingQueueConnection('redis') // or 'database'
    ->usingQueue('backups');
```

- Check the worker's `--timeout`, `--tries` and backoff, and the `failed_jobs` table. A failed backup is now retried and then recorded as failed.
- If the host disables `set_time_limit()`, v4 no longer crashes on it.

## 7. Verify

```sh
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan test --compact --filter=Backup   # any backup page tests
composer test                                  # or the project's full suite
```

If the plugin is registered in a panel, open the Backups page as an admin. Check that the listing loads, then trigger one database-only backup and confirm the queue worker picks it up.

## 8. Register the plugin in a panel (if it isn't yet)

1. **Move the package to `require`** if it was in `require-dev`. The panel provider loads it on every request, so a `--no-dev` deploy would otherwise fail with "class not found":
   ```sh
   composer remove --dev shuvroroy/filament-spatie-laravel-backup --no-update
   composer require "shuvroroy/filament-spatie-laravel-backup:^4.0"
   ```
   Confirm that `composer.lock` lists it under `packages`, not `packages-dev`.
2. **Register it** in the panel provider and restrict the page to admins. `authorize()` defaults to `true`, which means anyone who can open the panel:
   ```php
   FilamentSpatieLaravelBackupPlugin::make()
       ->authorize(fn(): bool => auth()->user()?->isAdmin() ?? false)
       ->timeout(80), // keep under the queue connection's retry_after (90 by default)
   ```
   Leave out `usingQueue()` unless the worker listens on that queue. A job on a queue nobody works fails silently.
3. **Define the gate abilities** that the plugin checks with `can()`. An undefined ability means "no", so without them the Create/Download/Delete buttons never show:
   ```php
   foreach (['create-backup', 'download-backup', 'delete-backup'] as $ability) {
       Gate::define($ability, fn(User $user): bool => $user->isAdmin());
   }
   ```
   If the project has a demo mode or shared demo logins, refuse all three there. A downloaded backup contains every user's personal data and the `.env`.
4. **Server:** `pg_dump`/`mysqldump` has to be on the PATH for the user the queue worker runs as. Backups go to the `local` disk (`storage/app/private`) by default. Consider an off-site disk and scheduling `backup:run` / `backup:clean`.
5. **Tests:** the page renders for an admin, `canAccess()` is false for everyone else, each ability is granted or refused as expected, and `create` pushes `CreateBackupJob` (`Queue::fake()`).

## What happened when run

- The package was in `require-dev` on v3.4.0, with spatie/laravel-backup 10.3.3 already installed. PHP 8.5, Laravel 13 and Filament 5 were all supported.
- The step 2 search found nothing. The plugin **is not registered in any panel**, so there were no API or queue changes to make.
- Upgraded to v4.0.2 with `--dev`. Only this package changed in `../composer.lock`.
- `../config/backup.php` wasn't published, so it was published and run through Pint. It already uses `health_checks`. Its notification address was changed from `your@example.com` to `ADMIN_EMAIL`, falling back to `SITE_CONTACT_EMAIL`.
- The full suite passed.
- Then registered on the admin panel (step 8): the package moved to `require`, the page is limited to `isBookseller()`, and there's an 80s job timeout. The job runs on the default queue, which production's Redis worker already processes (`_plans/deploy.md`). The three abilities are defined in `AppServiceProvider::defineBackupAbilities()`, and `DemoMode::BACKUP_ABILITIES` refuses them while the demo is on. Covered by `tests/Feature/Filament/BackupsPageTest.php`.
