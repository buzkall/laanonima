# La Anónima

Bookshop management built on Laravel 13, Filament 5 and Livewire 4.

## What's inside

- **Two Filament panels**: `/admin` (role `admin`) and `/client` (role `client`).
  Each role may only enter its own panel, as defined by `App\Enums\UserRole::panelId()`.
- **Passwordless access** via [`arzcode/filament-magic-login`](https://github.com/arzcode/filament-magic-login):
  a smart login that emails the user a magic link.
- **Book and publisher catalog**: full bibliographic record (ISBN-13/10, EAN,
  contributors, collection, binding, dimensions, subjects, price, stock…).
- **Automatic metadata by ISBN**: `FetchBookMetadata` queries Open Library and,
  when an API key is configured, Google Books; `DownloadBookCover` stores the cover.
- **QR generator** (`App\Filament\Pages\QrCodeGenerator`) with an SVG-composed logo.
- **Translations** in `lang/en` and `lang/es`. The default locale is `es`.

## Getting started

Requires PHP 8.3+, PostgreSQL and Redis. The site is served by Laravel Herd at
`https://laanonima.test`.

```bash
composer setup   # install + .env + key + migrate + npm install + build
composer dev     # server, queue, logs and Vite
```

Optional `.env` variables:

```
GOOGLE_BOOKS_API_KEY=      # without a key, Google Books is skipped (shared quota is exhausted)
BOOK_METADATA_USER_AGENT=  # identifier sent to the metadata APIs
```

## Common commands

| Command | What it does |
| --- | --- |
| `composer test` | Parallel suite with Test Impact Analysis (needs `pcov`) |
| `composer test:full` | Full parallel suite, no cache |
| `composer lint` | Laravel Pint |
| `composer types:check` | PHPStan / Larastan |
| `composer rector` | Rector + Pint over the changed files |
| `composer ci:check` | What CI runs: lint, rector, types and tests |

## Deployment

`./deploy.sh` takes a backup, puts the site in maintenance mode, pulls the code,
updates dependencies and the database, re-caches and brings the site back up.
Use `-b` to skip the backup and `-pre` to install dev dependencies too.
