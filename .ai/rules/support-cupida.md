---
paths:
  - 'app/Actions/Books/ImportShopBook.php,app/Actions/Books/EnrichImportedBook.php,app/Console/Commands/EnrichShopBooks.php,app/Actions/Cupida/RecommendBook.php,app/Support/Cupida/Recommendation.php'
---

# Support Cupida

## La Cupida files the book it recommends, and the split is pool vs network
The result screen links to *our* page for the book, so `RecommendBook::fileLocally()` writes a `books` row for a title only the shop had. It runs **between `decide()` and `record()`** and that ordering is load-bearing: `record()` writes `book_id`, so filing afterwards leaves every automatically catalogd row reading "not in our catalog" in the panel forever.

The split is pool vs network, not foreground vs background. `ImportShopBook` takes a `resources/data/cupida/books.json` entry and makes the row with **no network at all** — that is why it fits inside the wait the reader is already spending on the model, and why nothing polls. `EnrichImportedBook` (ISBN lookup + cover) is `defer()`ed; there is still no queue worker on this site.

Traps, each found the hard way:

- `defer()` runs in the reader's own PHP process under the `max_execution_time` that request has been spending since it began, so `EnrichImportedBook` calls `set_time_limit(books.metadata.enrich_time_limit)` first. Without it the process is killed mid-lookup and you get a fatal in a terminating callback with no warning of ours beside it. The 120s is sized for every provider being unreachable (each retries twice), not for the good afternoon.
- `metadata_synced_at` is stamped whether or not anything was found: it records that we looked. Most of these are recent Spanish titles no free source knows, and stamping only hits makes `books:enrich` re-ask the same questions forever.
- `ImportShopBook::existing()` deliberately has no `active()` scope, unlike `Recommendation::localBook()`. An un-published book still owns the unique `isbn13`, so a lookup that cannot see it inserts a duplicate and fails inside a reader's request. An inactive row returns null: leave the bookseller's decision alone and let the CTA stay on the shop.
- `stock` is written as 1 from the pool's `available`, because `books/show.blade.php` decides its call to action on `stock > 0`. Left at the column's zero, a book we have just recommended greets the reader with "no lo tenemos" and the request form.
- The panel's artwork does not need freezing: the result renders once and is never re-rendered, and a row filed a moment ago has no media, so `Recommendation` falls back to the shop's `imagen.php` and the default palette on its own.
- `cupida.import.daily_cap` is not about cost. `recommend()` is unauthenticated and `cupida.rate_limit` only guards the paid pitch, so it is the only thing between a script walking the deck and five thousand unreviewed rows on the public shelf.
- `books:enrich` is the catch-up sweep for whatever the deferred pass missed (closed tab, hung provider). Gap-fill only, safe to re-run.
