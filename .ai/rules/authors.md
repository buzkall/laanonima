---
paths:
  - 'app/Models/Author.php,app/Models/Book.php,app/Filament/Resources/Authors/**'
---

# Authors

## People are rows in `book_contributors`, not JSON on the book
`authors` (name, unique slug, bio) is one record per person, matched by slug so the same name on two books is one row (`Author::named()`). `book_contributors` (book, author, `role` cast to `ContributorRole`, `position`) is the title page: one row per person and role, so the same person can author one book and translate another, or hold two roles on one. The panel edits it through `Repeater::make('contributors')->relationship()->orderColumn('position')`, whose `author_id` select opens a "new author" modal built from `AuthorForm::quickCreateFields()` so the two forms cannot drift.

`books.authors_line` is still a denormalized column (search, sorting, listings, the shelf's data attribute) but is no longer computed in `Book::saving()`: `BookContributor::saved/deleted` and `Author::saved` (on rename) call `Book::syncAuthorsLine()`, which writes the row directly. `Author::deleting` deletes its contributions through the models rather than leaving it to the FK cascade, precisely so those events fire. Never write `authors_line` by hand.

`Book::syncContributors([['name' => ..., 'role' => ...]])` replaces the whole title page from names, and is what the seeder and `BookFactory` use: the factory still accepts `'contributors' => [...]` in `create()` for readability, lifting it out of the attributes in `newModel()` and filing it in `configure()`'s `afterCreating`. `LookupIsbnAction` files the people a source names as authors on the spot and only fills the repeater when no row has an author yet.

`Book::authors()` is role author only (the big line under the title, and `alsoByAuthors`); `Author::books()` and the public `/autor/{author}` page are every role, so a translator has a page too. `bio` holds HTML from a `RichEditor`; render it with `{!! !!}` inside `.rich-text` (styles in `resources/css/app.css`) and use `Author::bioExcerpt()` wherever plain words are needed, such as a meta description or a table column. The `authors` filter on `BooksTable` is hidden inside the authors' `BooksRelationManager`, as the `publisher` filter is inside the publishers'. A book on someone's page is listed with a `contribution` badge column (`Book::rolesFor()`), visible only inside that relation manager, because the row's own `authors_line` says nothing about why the book is there.
