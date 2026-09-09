---
paths:
  - app/Models/Subject.php
  - app/Models/Book.php
  - app/Console/Commands/ImportBookSubjects.php
  - database/seeders/SubjectSeeder.php
  - 'app/Support/BookMetadata/**'
  - 'app/Support/Shop/**'
  - 'app/Filament/Resources/Books/**'
---

# Book subjects

## Book subjects (materias)
What a book is about is `books.subject_id` → `subjects`, one node of THEMA in the Spanish the shop itself publishes. It replaced a `jsonb` array of `{scheme, code, heading}` that nothing could filter, count or offer as a list.

## A code is its own path — never write a recursive query
THEMA nests by prefix: FMM under FM under F, spelled out in the code. So "everything filed under fantasy" is `Subject::withinTree('FM')` → `code like 'FM%'`, left-anchored, served by the unique index, and identical on Postgres and SQLite (which matters — the suite runs on the second, production on the first). `parent_id` is there to render the tree in a form, not to search it. Do not reach for a recursive CTE, a nested set, or a pivot of ancestors.

## One subject, not many
Measured over 5,393 real books off the shop: 68% carry two codes, but 99% of those are one lineage written twice (FK beside FKM, FM beside FMM). Only twenty books in five thousand are genuinely about two unrelated things. The column holds the most specific code and everything broader follows from it. If those twenty ever matter, add a `book_subject` pivot beside the FK — do not change what the column means.

## The ISBN lookup deliberately does not fill it
Google Books answers with free text and no code ("Fiction / Fantasy"); Open Library's subjects are reader-contributed tags — one record yields "Girls", "Time", "tortoises". Neither can name a THEMA code, so `BookMetadata` carries no subjects at all and the providers' own words survive in `books.raw_metadata`. Which materia a book belongs to is a bookseller's judgment, made in the panel.

## The tree is fetched by hand and committed
`php artisan books:import-subjects` walks the shop's `/materia/` pages breadth first and writes `database/seeders/data/subjects.json`; `SubjectSeeder` reads it with no network at all, so `migrate:fresh --seed` is reproducible offline and in CI. The walk is resumable (`opened` per node — an unmarked leaf is one the next run asks about again) and skips THEMA's qualifier axes, the codes starting with a digit: `5A` says who a book is for, not what it is about, and that is not what `subject_id` means.

`ShopScraper` lives in `App\Support\Shop`, not `App\Support\Cupida`, because the real catalog must not depend on a namespace that gets deleted when La Cupida's stand-in pool does.

## Wording
Table `subjects`, model `Subject`, panel label **"Materia"** — the shop's own word, and what `lang/es/books.php` already said. Readers see **"Género"**, which is what La Cupida's cards say. Do not rename one to match the other.
