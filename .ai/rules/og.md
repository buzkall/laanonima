---
paths:
  - 'app/Models/Author.php, app/Support/Og/OgCard.php, tests/**'
---

# Og

## Never combine Author::books() with onShelf() — Postgres rejects it
`Author::books()` is `belongsToMany(...)->distinct()`. Adding `onShelf()` to it produces `SELECT DISTINCT` with an `ORDER BY` naming expressions that are not in the select list (`is_featured`, `published_on is null`), which Postgres refuses with SQLSTATE 42P10.

Use the query the pages already use instead:
`Book::query()->onShelf()->whereHas('contributors', fn($q) => $q->whereBelongsTo($author))`

Why this bites: the suite runs on SQLite, which executes the distinct form happily, while local and production run Postgres. A green test suite is not evidence here. This is the same class of divergence as the NULL-ordering note in `.ai/rules/frontend.md` — anything touching shelf ordering wants checking against Postgres by hand.
