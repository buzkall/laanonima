---
paths:
  - 'app/Actions/Portraits/AttachAuthorPortrait.php,app/Console/Commands/FetchCupidaPortraits.php,app/Models/Author.php,resources/views/books/author.blade.php'
---

# Views Books

## Author portraits are filed twice, and the two are keyed by different slugs
`cupida:portraits:fetch` stores the JPEG on the `portraits` disk under the shop's slug ("guerriero-leila", surname first) for the card, and then also files it as `Author::PORTRAIT_COLLECTION` media on the shop's author record, whose slug is `Str::slug(PersonName::normalize(name))` ("leila-guerriero"). `AttachAuthorPortrait` bridges the two through the row's `name`; it links only, never creates an author. It runs over what was already on disk too, so a writer added after the deploy gets a face on the next run, and it only overrules an existing portrait when it just downloaded one (`--force`), so a photo the bookseller chose in the panel stays. The Commons credit rides in the media's custom properties (`credit.artist/license/...`) and the public author page prints it under the portrait; a panel upload has none and prints nothing.
