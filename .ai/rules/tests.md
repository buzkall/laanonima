---
paths:
  - 'tests/**'
---

# Tests

## Pin every searchable field on the row a search test must not find
`->searchTable('Marta')` also searches the columns you did not think about: `user.name` is `->searchable(['name', 'email'])`, and the es_ES faker builds `safeEmail()` out of a first name, so `User::factory()` hands the decoy reader `marta.llamas@example.net` about once in 250 rows and the row the test asserts it cannot see turns up. It fails in one full-suite run out of a handful and passes on its own, which reads as flakiness and is not.

Write out every field the table searches on the decoy row -- name, email, title, publisher, isbn -- rather than leaving them to the factory. Check the table's `->searchable()` calls, including the array form, before deciding what has to be pinned.
