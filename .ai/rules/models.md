---
paths:
  - app/Models/Book.php
---

# Models

## Authors are a slug column, not a table
There is no authors table and no Author model. A person's page (`/autor/{slug}`) is keyed by the slug alone, matched against `books.author_slugs` — a jsonb array the `saving()` hook writes from the contributors JSON, right next to `authors_line` and from the same `authorsAmong()` helper. Both are denormalizations of the same field; change one and change the other.

Slugging is lossy, so the display name never comes from the URL: `BookController::authorName()` reads it back off the first book on the page. Every link and lookup goes through `Book::authorSlug()`, so the two sides cannot drift.

Only role `author` gets a slug. A translator listed in "Quién lo escribe" is plain text, and `/autor/<their-slug>` is a 404 — deliberate, the page is "books by this author", not "everyone who touched this book".

Publishers need none of this: `Publisher` has a unique `slug` and `#[RouteKey('slug')]`, so `/editorial/{publisher}` is plain route-model binding.
