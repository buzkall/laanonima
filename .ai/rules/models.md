---
paths:
  - app/Models/Book.php
---

# Models

## The public author page is route-model binding on `Author`
`/autor/{author}` binds `Author` by slug (`#[RouteKey('slug')]`), like `/editorial/{publisher}`; there is no `author_slugs` column any more. `BookController::author()` lists what is on the shelf with a `book_contributors` row for that person, in any role, and 404s when there is none. Translators get a page and a link of their own: every name in "Quién lo escribe" leads to one. The bio, when written, leads the page above the stock intro line, and is rendered as HTML from the panel's rich editor.

Publishers need none of this: `Publisher` has a unique `slug` and `#[RouteKey('slug')]`, so `/editorial/{publisher}` is plain route-model binding.
