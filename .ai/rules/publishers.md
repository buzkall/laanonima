---
paths:
  - 'app/Models/Publisher.php,app/Support/PublisherName.php,app/Support/PublisherLogos/**,app/Actions/Publishers/**,app/Actions/Books/ImportShopBook.php,app/Filament/Resources/Publishers/**,app/Console/Commands/FetchPublisherLogos.php,config/publishers.php'
---

# Publishers

## A publisher row is filed by Publisher::named(), never by firstOrCreate
`Publisher::named($name)` is the only way to file a publisher from a catalog (`ImportShopBook`, `LookupIsbnAction`). It runs the name through `PublisherName::normalize()` first, then slugs the tidied name — which is what stops "PLAZA & JANES" and "PLAZA &amp; JANES" opening two rows — and recases a row filed while it was still shouting.

`PublisherName` only recases a name written in nothing but capitals, the same rule as `PersonName`: one lowercase letter anywhere means the source cased it on purpose ("AdN", "Duomo ediciones"). Anything that is not a word is kept as it is — legal forms with a period, runs of two letters, runs with no vowel — so add a real acronym to its `ACRONYMS` list rather than loosening a rule.

Names alone cannot close a duplicate that is more than capitals ("Caja Negra" vs "Caja Negra Editorial"). That is `MergePublishersAction` on the listing: the row the button is on survives, the rest are absorbed. `MergePublishers` moves the books *before* deleting, inside a transaction — `books.publisher_id` is `nullOnDelete`, so the other order silently strands a shelf. It is authorized on its own `merge` policy ability, not `delete`, so it stays available in demo mode: nothing is lost with the absorbed rows. Do not switch it back to `delete` — that hides it from the demo.

`php artisan publishers:tidy` is the one-off pass for rows filed before any of this; it renames only, and a slug never changes.

## Logotypes come from Wikidata first, then the publisher's own website
`FetchPublisherLogo` is the one path: `publishers:logos` runs it for every publisher with no logo that nobody has asked about (`logo_checked_at` is stamped hit or miss), and `FetchLogoAction` on the listing runs it for one row with `replace: true`. A logotype already there is only swapped once a new one has actually downloaded.

`WikidataPublisherSource` accepts a candidate only if its P31 is in `publishers.logos.publisher_types` **and** the text the search matched slugs to the name searched — "Taurus" is a constellation and a missile first, and the search is by prefix. Do not relax either guard to raise coverage; `--only` with `--qid` names an item by hand. Wikidata finds nothing for "Norma Editorial, S.A.", so the name is retried without its legal form, then without one generic word.

A website from P856 fills `website` only when it is blank, never over one somebody typed. Icons are never taken from `website.shared_hosts` (publishing groups like Penguin Random House, whose icon is not the imprint's) — grow that list rather than special-casing an imprint.

Website fetches go through `PublicUrl`, not `RemoteImage`: the host is arbitrary, so the guard resolves it and refuses any non-public address, pins the request to the checked IP and re-checks every redirect. Commons thumbnails still use `RemoteImage` with `allowed_hosts`.

Logos are stored as PNG with alpha (`DownloadPublisherLogo` never flattens onto white), and the `thumb` conversion needs `keepOriginalImageFormat()` — without it media library writes JPEG and a transparent logo renders on black. GD is the only image driver: SVG and ICO are skipped everywhere (Commons serves SVG logos as `.svg.png` thumbnails), and `og:image` is never used because real sites put a share banner there.

