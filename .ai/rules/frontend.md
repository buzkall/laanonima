---
paths:
  - 'resources/views/books/**'
  - app/Support/CoverPalette.php
  - app/Http/Controllers/BookController.php
  - resources/views/books/index.blade.php
  - resources/views/books/show.blade.php
---

# Book page

## The page is painted in the book's own cover colour
`books.cover_color` is not decoration: `CoverPalette::fromCover()` turns it into the
three colours the whole page is built from, emitted as custom properties on `<body>`
(`--cover`, `--on-cover`, `--accent`, `--rule`) and read back through Tailwind
arbitrary values (`bg-[var(--cover)]`). Only cream and ink are fixed.

Every colour that is not read off a cover, and the two thresholds that derive the
other two, live in `config/site.php` under `site.palette` — `CoverPalette` holds no
colour constants any more. Cream and ink are the exception that is written twice:
Tailwind cannot read PHP, so `--color-paper` and `--color-ink` in the `@theme` block
of `resources/css/app.css` repeat `site.palette.paper` and `site.palette.ink`. Change
one and change the other.

Both derived colours are decided by WCAG contrast, not by a lightness threshold,
because the averaged colours `ExtractCoverColor` produces land anywhere from a washed
pink to a near-black brown: the foreground is whichever of cream/ink reads better over
the cover colour, and the accent is that colour walked towards black until it clears
`site.palette.min_contrast` against the cream page. Never hardcode the reference red —
a book with no cover gets `site.palette.fallback`.

The colour is only *derived* while the column is empty: a bookseller can pick it by
hand in the panel, and whatever is stored is then left alone (see `.ai/rules/app.md`).
So `cover_color` is not guaranteed to match the cover, and everything downstream must
keep reading the column rather than the image.

## One breakpoint, named `wide`
`--breakpoint-wide: 1000px` in `resources/css/app.css` is the page's only breakpoint.
Below it everything is one column; at and above it the cover leaves its column and
floats `position: fixed` over the page, sliding left on scroll. The inline script in
`show.blade.php` owns that: it also re-measures the coloured band (which is only as
tall as the hero) on `document.fonts.ready` and through a `ResizeObserver`, otherwise
a cream stripe appears while the webfonts load.

## The floating cover's box is derived, never guessed
Two numbers have to keep agreeing or the cover walks over the text. The grid is
`0.92fr 1.08fr`, so the column the fixed cover stands in for is **46%** of the window
-- not 50vw, which is what the reference design used and what overlapped the headline
at 1000-1300px. Use a percentage rather than `vw`: a fixed element resolves it against
the initial containing block, so a classic scrollbar is already excluded.

Vertically the container is anchored `top: var(--top-bar); bottom: 0`, so its height
follows from the two edges and the image is simply `max-h-full`. `--top-bar` is the
measured height of the page header, written by the inline script on every paint (with
a `57px` default inline on `<body>` for first render). Pinning to `top: 0` with a
`clamp()` of padding instead -- the reference's approach -- puts the cover under the
top bar at every width where the clamp is shorter than the bar.

## `@fonts` takes explicit aliases
The directive with no argument emits every family in `vite.config.js`. Pass the ones
the page actually uses — `@fonts(['gloock', 'crimson-pro'])` — so the sans-serif pages
do not preload the serifs, or the other way round. Aliases are the slugified family
names; `public/build/fonts-manifest.json` lists them after a build.

## An administrator can preview an unpublished book
`BookController::show()` 404s a book with `is_active = false` for everyone except an
admin. That is deliberate: the "Ver en la web" header action on `EditBook` is most
useful precisely while the record is still being catalogued.

## The shelf wears the house red, each card wears its own book's colour
`/` is `BookController::index()` and renders `books/index.blade.php`. The listing belongs to no book, so its body palette is `CoverPalette::fromCover(null)` — the house red, never a literal.

Each card is its own small version of the same idea: the cover box is painted `CoverPalette::fromCover($book->cover_color)` and the image sits `object-contain` on top, so a portrait, a square and a missing cover all fill the same 2:3 box. A book with no image gets its title set in Gloock over that colour instead.

Order is `is_featured` desc, then publication date desc. `published_on is null` is sorted explicitly before the date, because a NULL sorts first in a descending order on Postgres and last on SQLite — the shelf must not depend on the driver.

Pagination is hand-rolled prev/next (`books.public.home.prev` / `.next`) rather than `$books->links()`: the framework's Tailwind paginator is grey and off-brand.

## Bands step clear of the floating cover; they are never drawn over it
The publisher band used to centre a 640px column in the window and lift it with `relative z-40` so the fixed cover (z-30) would not hide it. That put its rules across the cover image. It now clears the cover column instead — `wide:pl-[46vw] wide:pr-[clamp(22px,5vw,80px)]`, the same move the contributors band makes — and the z-index is gone. Any new full-width band below the hero has to do the same.

The cover carries a two-layer ink shadow at every width so it reads as a book lying on the page rather than as part of the flat band. That is why the cover column is no longer `overflow-hidden` below `wide:` and has `pb-12`: without the room the shadow was cut off at the container edge.

Extra images (`Book::gallery()` — everything in the `covers` collection after the first) are shown as a small auto-fill grid at the top of the "El libro" section, where the faked cover-detail crop used to be. Use `auto-fill`, not `auto-fit`: with one extra image `auto-fit` collapses the empty tracks and blows it up to the full column width.
