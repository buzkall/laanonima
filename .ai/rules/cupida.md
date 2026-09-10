---
paths:
  - 'app/Support/Cupida/**'
  - 'app/Ai/**'
  - 'app/Actions/Cupida/**'
  - config/cupida.php
  - app/Livewire/Cupida.php
  - app/Console/Commands/ScrapeCupidaCatalog.php
  - app/Models/CupidaPrompt.php
  - app/Models/CupidaRecommendation.php
  - 'app/Filament/Resources/Cupida/**'
  - app/Filament/Actions/EditCupidaPromptAction.php
  - 'resources/views/cupida/**'
  - resources/views/livewire/cupida.blade.php
  - lang/es/cupida.php
  - lang/en/cupida.php
  - resources/css/cupida.css
  - 'app/Support/Portraits/**'
  - 'app/Actions/Portraits/**'
  - app/Console/Commands/ResolveCupidaPortraits.php
  - app/Console/Commands/FetchCupidaPortraits.php
  - app/Support/Cupida/CupidaPortrait.php
  - resources/views/cupida/contact-sheet.blade.php
  - 'app/Support/Cupida/**,config/cupida.php'
  - resources/views/components/site-footer.blade.php
  - resources/views/components/layouts/shelf.blade.php
---

# La Cupida
The recommender at /la-cupida: an opening card, three rounds of six swipe cards, then one book.

## The model never chooses out of the catalog
`CupidaShortlist` scores the whole pool in PHP and sends the best 30; `CupidaAgent::schema()` pins `ean` to those 30 with `->enum()`, so a book the shop does not stock is not a bad answer to catch downstream, it is not a possible answer. Keep the enum. It is the only reason this is safe to put on a real shop's public page.

With no `ANTHROPIC_API_KEY`, a provider failure, or the rate limit reached, `RecommendBook` falls back to the top of its own shortlist plus `cupida.result.fallback_pitch`. The page must never 500 and must never need a key to demo.

## The pool is three committed JSON files, never a live request
`resources/data/cupida/{themes,authors,books}.json`, written by `php artisan cupida:scrape` by hand and committed. Nothing regenerates them on deploy or in CI, and the app only reads them, so the shop being down cannot take the page with it. `CupidaCatalog` is a singleton (registered in `AppServiceProvider`) that reads them once.

## The author portraits are a fourth file, and that is not tidiness

`ScrapeCupidaCatalog::authors()` rebuilds its whole array from the books pool
on every write. A `photo` key added to `authors.json` therefore survives exactly
until the next `cupida:scrape` and then vanishes with no error at all -- so the
portraits live in `resources/data/cupida/author-photos.json`, keyed by slug,
which the scrape never touches.

**The metadata is committed and the JPEGs are not.** `cupida:portraits:resolve`
asks Wikidata and Commons, is run by hand, and writes the file;
`cupida:portraits:fetch` reads the recorded `image_url` and downloads the images,
and is a deploy step (it is in `composer setup`, and it needs a line in the
deploy script). That is why `CupidaCatalog::portrait()` checks the disk as well
as the row: a deploy that skipped the fetch has every verdict and no image, and
the card has to fall back to no face rather than to a broken one. One directory
listing per request answers it for all six cards -- never a `Storage::exists()`
per card.

A pool of seven hundred also means the deck reaches entities the shop files
under an author name that are not writers at all -- "Shine", the production
company behind the MasterChef books, was dealt as a card before it was marked
`--none`. Most of the pool has never been through a portraits review, so nothing
has said "not a person" about it. When one surfaces, `--none` is the answer; a
spelling that will come back with the next scrape belongs in
`collective_patterns` instead.

`cupida.portraits.pool` is what the resolve command walks, and it is not the
deck's floor. Every name costs two requests whether or not it comes back with a
face, and the answers are reviewed by hand, so it is sized as an afternoon's work
and raised a chunk at a time. The run resumes, so nothing recorded is asked twice.

`status` carries five verdicts and the misses matter as much as the hits: without
a `no_match`/`no_image` row, the names Wikidata will never answer cost two
requests apiece on every run, forever. `pinned` rows are what a person decided by
hand, and `--fresh` **keeps them** -- deliberately unlike `cupida:scrape --fresh`,
because an afternoon of reviewing faces is not something a flag gets to destroy
silently. `--forget-pins` is how to mean it.

## The occupation guard is what keeps a stranger's face off a card

`WikidataPortraitSource` takes the first candidate that is both `P31=Q5` and has
a `P106` in `cupida.portraits.occupations`. Drop the second half and searching
"Mary Oliver" reaches a Dutch jazz singer (who has a photo) instead of the poet
(who does not), and "Michael McDowell" reaches an Irish politician instead of the
novelist. Measured over the whole 150-name portraits pool it finds 118 faces; over a
19-name sample checked by hand, unguarded gave thirteen photos of which two were
the wrong person, guarded gave eleven and none wrong. Losing two faces to keep
two strangers off the cards is the trade. Do not relax it to raise the number.

What it cannot catch is a correctly identified writer whose only photograph is a
statue, a book cover or a group shot -- and it cannot know that Carmen Mola is
three people. That is what `--sheet` and the two pins are for, and why the review
step is part of the job rather than a nice-to-have.

## "Vv. Aa." is not a person and never gets dealt

The pool is a scrape of a shop's author field, so it carries anthology markers
("Vv. Aa.", "Varios Autores", "Vv.Aa.3") and shared pen names among the writers.
"¿Te gusta Vv. Aa.?" is a card nobody can answer. They are recorded as
`status: no_person`, and `CupidaDeck::authorCards()` drops them in the same pass
as the floor.

The recurring shop-ism is matched by `cupida.portraits.collective_patterns`,
because it comes back under a new spelling every time the catalog grows. That
check lives in `CupidaCatalog::isCollectiveName()` and is asked twice: by
`cupida:portraits:resolve` before any request is made, and by the deck on every
draw. Both are needed. author-photos.json only reaches `cupida.portraits.pool`
names and the deck reaches every writer above the floor -- several times
further -- so a `no_person` row cannot be the only answer. A shared byline with
a real Wikidata item is a judgment call no pattern expresses: mark it with
`--none`.

## The photo credit sits under the deck, and is not a link

Commons portraits are mostly CC BY-SA with attribution required, and every one is
recropped to the card -- so the line under the buttons names the photographer,
gives the license, and says it was cropped. All three are the license's asking,
and `portraits.credit` in `lang/{es,en}/cupida.php` holds it.

It reads `$stack[0]`, the card actually in front of the reader, and renders
nothing when that card has no face.

Two things it is not, both tried first:

- **Not on the card.** Inside the card it has to wrap within the 58% the photo
  occupies, and it lands on top of the writer's name -- Tolkien's credit ran to
  three lines. It would also fly off with the card mid-swipe.
- **Not a link.** It sits outside `.cupida-stack` so it cannot swallow a drag,
  but an anchor by the buttons is still a navigation next to the two controls
  the whole page depends on. The source URL is in author-photos.json, which is
  the record; the page carries the names.

`CupidaPortrait::artistLabel()` truncates, because Commons' `Artist` is free text
and some of it is provenance rather than a name.

There was a credits list at the foot of /la-cupida naming every portrait we hold.
It is gone on purpose: a reader meets six author cards and the list credited a
hundred and thirteen photographs, nearly all for faces that session never showed.

## The authors round has a floor, not a ranking

`cupida.deck.author_min_books` is two, and every writer at or above it is
dealable. It was `author_pool`, the best-stocked 150 names, and that is worth
knowing because of how it failed rather than because it was wrong to try.

There has to be some floor: 2,798 of the 3,512 names in the pool have exactly one
book, so a flat shuffle deals six writers nobody has heard of. But a **rank** cut
lands wherever it lands. The 150th name sat in the middle of the four-book tie,
which the scrape breaks alphabetically, so the last fifth of the pool was frozen
as the four-book writers sorting before "Masashi" -- and the twenty-odd with the
identical claim sorting after it could never be dealt at all. A floor on the books
has no inside and outside to be arbitrary about.

Two rather than four is a trade with a named price. Six drawn out of 150 repeats
a writer about every fourth session, which a reader who plays twice in a week
notices; six out of 685 does not. In exchange the pool reaches further down the
shelf, so more cards are names to be met rather than recognized. Do not read a
run of unfamiliar cards as a bug -- that is the setting, and the lever is this
number.

`cupida.portraits.pool` is a **different** number on purpose (see below): raising
the deck's reach costs nothing, raising the portraits' reach costs an afternoon
of Wikidata requests reviewed by hand. Most of the pool is dealt faceless, which
is the ordinary case the card was always built for.

`useCupidaFixture()` lowers the floor to one, the way it lowers
`deck.min_books`: the fixture is eleven authors and one of them has two books.

## `books` counts titles, never copies

`ScrapeCupidaCatalog::authors()` counts a writer's **distinct** titles. The shop
stocks a novel in hardback, paperback and an illustrated edition -- three rows,
one book -- and counting rows put James Islington (two novels, four editions)
above writers with three of their own. A floor has to mean something.

The same count orders `titles`, whose first entry is the card's subtitle. Depth
of stock is the only signal a listing carries about which of a writer's books
somebody might have heard of, and it is a real one: it changed 203 of the 685
subtitles, and it is why García Márquez's card reads "Cien años de soledad"
rather than "Cien años de soledad (edición ilustrada)". Ties break on the title
itself so a re-scrape meeting the listing in another order cannot silently reword
every card.

`authors.json` is a function of `books.json`, so a change here only reaches a
reader once the file is written again. `cupida:scrape --rebuild` does exactly
that and asks the shop nothing -- an afternoon of traffic to a small bookseller
for an answer already on disk is not a trade worth making.

## The shop's listing loses the letters Spanish does not use

There is not one å, ø or æ in five thousand books, and where one belongs the
shop leaves a space. Nothing in `ShopScraper` does this -- the pages are
ISO-8859-1, the conversion happens before parsing and every Spanish accent in
the pool is intact -- so it is their data, and a re-scrape brings it back every
time. That is why the fix is `cupida.scrape.author_aliases`, keyed by the shop's
spelling exactly as it appears, and not an edit to books.json.

Knausgård is the case that shows why it matters: filed as both "Knausg rd" and
"Knausgard", he was two authors with a book and two books, neither deep enough
to be dealt. `ScrapeCupidaCatalog::canonicalAuthor()` runs on both ways in --
the listing and the pool already on disk -- and rewrites **books.json**, not
just authors.json, because `CupidaShortlist::authorOf()` slugs that same string
to match a book against a liked card. Corrected in one file and not the other is
a card that scores none of its own books.

Known and not fixed: "Kamo No, Ch& X0014d" is a numeric entity that arrived
already broken, not a lost character, and guessing at it is a different job.

## The scrape resumes; build the pool over several runs
Every run reads the committed JSON first, adds to it, and re-fetches nothing it has. The synopsis pass is one request per book and only looks at books without one; `--limit` takes a chunk and the closing line reports what is left. `--fresh` is the only way a dropped book leaves the pool. Never try to fetch the whole catalog in one sitting -- that is what gets the address blocked.

## Pool size costs scoring, not decoding
Decoding 5k books is ~9ms; scoring them is ~120ms and was ~450ms until `CupidaShortlist::fold()` dropped `Str::ascii()` for a `strtr` over the Spanish accents. It runs over every book's title and synopsis on every recommendation. Do not put `Str::ascii()`, `Str::slug()` or anything else with a transliteration table back into that path.

## Scraping laanonimalibreria.com: two silent traps
1. A cold request gets a two-line page that sets `ew_hc` in JavaScript and reloads. Nothing works until it comes back and no header gets past it, so `ShopScraper` reads the value out of that first body.
2. Every page is ISO-8859-1 and says so in a meta tag. Converting the bytes to UTF-8 is not enough — DOMDocument believes the meta tag over you and decodes twice, so "Ficción" becomes "FicciÃ³n". `asAscii()` hands it numeric entities instead. Nothing throws either way; it surfaces as mojibake in a committed JSON file. The scraper has no tests (it runs by hand, rarely), so check the accents in the JSON after a run.

Pagination is a module id and a subject code that cannot be rebuilt from the pretty URL — follow the shop's own next-page link, never construct `?p=2`. Be slow (`cupida.scrape.delay_ms`): the address gets blocked otherwise, which happened while this was written.

## The covers round is out, and the deck is a function of its seed again
There was a fourth round of real books with covers, drawn from a shortlist scored on the first three answers. It is gone from `CupidaDeck` (git has it, up to the commit that dropped it), so `for()` takes a seed and nothing else and the component memoizes the deck for the request instead of rebuilding it after every swipe.

What is left of it on purpose: `CupidaShortlist` still scores `book:` likes and still excludes `book:` passes, because sessions recorded while the round existed are still read back by the panel -- and because the round is meant to come back. An empty round is still stepped over by `Cupida::advance()`; a round with no cards can never be completed.

## A mood lives in two files and the list is deliberately much longer than the deck
`config('cupida.moods')` holds the keywords a mood is scored by; `lang/{es,en}/cupida.php` holds the card a reader actually reads. A mood added to one and not the other is either an untranslated key on a card or a card nobody can be dealt, and only the es/en halves are checked by `TranslationsTest`.

Nearly forty moods for a deck of six, on purpose: six drawn out of ten is most of the list every session and two readers meet nearly the same question. The width is also what lets a mood be narrow ("que me dé hambre") -- a bad card to deal every time, a good one to meet once.

Keywords are matched by `CupidaShortlist::moodScore()` against the folded title and synopsis only, never the subject headings, and one hit scores the mood once. Write stems, without accents ("amist", not "amistad"), and keep them long enough not to match inside another word.

## One answer at a time
`spentKey` only guards the card just answered, and a re-render promoting the next one satisfies it again. The `busy` flag in the inline script is what stops a second card being answered mid-flight (two cards half off screen, server on a different round). It clears on the promise and again on a 2s fallback, because a deck that never unlocks is worse than one that unlocks early.

## Alpine + Livewire: `$root`, never `$el`; bind to what survives a morph
The swipe deck is inline in `resources/views/cupida/index.blade.php`, not in `resources/js/`. `app.js` is a deferred module and Livewire injects a plain script before `</body>`, so Livewire boots Alpine and `alpine:init` fires before app.js runs — anything registered from there is always too late.

Inside an `Alpine.data` method, `$el` is whichever element the expression being evaluated is bound to: the stack when the gesture calls it, the *button* when a button does. `this.$root.querySelector('.cupida-card--top')` is the only version that works from both, and getting this wrong looks exactly like a swipe that silently does not register.

Bind `@pointerdown` etc. to `.cupida-stack`, not to the top card. Livewire's morph patches attributes onto already-initialised elements, so a card promoted to the top never gets an Alpine binding or an `x-ref`.

Guard a spent card by its `wire:key` (`spentKey`), not a boolean reset in a promise callback — a flag cleared in `.finally()` stays set forever the one time it does not run.

## Everything the reader could tamper with is `#[Locked]`
The answers decide what goes into a prompt the shop pays for. The component keeps an EAN and two strings, never the `Recommendation` — it carries a `CoverPalette` and sometimes an Eloquent model, neither of which belongs in a round-tripped payload — and rebuilds it in `render()`.

## The cost is worked out here, never returned by the provider
`Laravel\Ai` answers with token counts (`$response->usage`) and no price, so `PromptCost::of()` multiplies them by the per-million rates in `cupida.prices`. `cupida_recommendations.cost` is only ever as right as that list: when the column stops matching the Anthropic invoice, the rates are what went stale, not the arithmetic.

Both the stored `model` and the price key come from `$response->meta->model`, never from `cupida.model`. The config says what was asked for; the response says what answered. They agree today because `RecommendBook` passes the model explicitly, and they would stop agreeing the moment anything else picks one — `#[UseCheapestModel]`, a per-request override, a provider substituting a snapshot id. A row that names the wrong model is priced wrong too.

A fallback line prompts nothing, and a model with no entry in `cupida.prices` cannot be priced. Both are stored with a null cost rather than a zero -- an empty column says "we do not know what this cost", a zero would say it was free.

## Both picks are kept, only one is shown
`record()` stores `$shortlist[0]` in the `shortlist_*` columns beside whatever the model chose, plus `shortlist_rank` -- where the model's pick sat in the thirty. It is free, because the shortlist is scored before anything is prompted, and it is the only way to ask afterwards whether the model is doing anything the scoring was not. Nothing about it reaches the page: `Recommendation` never carries it, so a reader still sees one book.

The panel shows the scoring's own pick on every card, agreement included (`shortlistPick()`), because the question it answers -- what would this reader have been given with no model -- has an answer on every session. Drawn only where the two differ, silence would have to be read as agreement.

`shortlist_rank` is kept and no longer displayed. As a badge it made a reader do arithmetic to answer a question the title answers directly; it stays because the "coincidió con la lista" filter and the "donde más se alejó" sort are both cheap on it. A canned line falls outside both sides of that filter on purpose -- the fallback *is* the top of the shortlist, so it agrees by construction, and counting it would flatter the model with every failure.

## Running out of credit is not an outage, which is why it needs a mail
Anthropic maps a credit refusal to `InsufficientCreditsException` (a 402, or a 400 whose message matches `AnthropicGateway::insufficientCreditPatterns()`), and `RecommendBook`'s `catch (Throwable)` turns it into the fallback. So the page stays up and readers keep getting books -- picked by the scoring, with the canned line. Nothing looks broken; the pitches just stop being written. `WatchCupidaCredit::afterRefusal()` is what makes that visible.

There is no balance to read. Anthropic publishes none, and the admin reports say what was spent, so `CupidaCredit` counts down from a number a bookseller typed in after topping up. It warns and never blocks: the figure is only right while nothing else spends the key, and the provider's own refusal is what actually stops a prompt.

`afterSpending()` must run after `record()`, never inside `decide()`. What is left is the balance minus the rows, so a check that runs before the row is written has not seen the dollar just spent.

**Both warnings are `ShouldQueue`, and that is the one thing here with a server dependency.** They are raised inside a reader's swipe, so an unreachable SMTP host would otherwise be added to the wait for their book. The price is that the site needs a queue worker: on Forge that is the site's Queue tab (a Supervisor `queue:work` daemon on the `database` connection), plus `php artisan queue:restart` in the deploy script so a worker does not keep serving the old code. Setting `QUEUE_CONNECTION=database` on its own runs nothing -- the job waits in `jobs`, no error, no log, and nobody is told the pitches have stopped. The rest of the app's mail (`BookRequest`) is still sent inline and does not depend on this.

## The prompt has two halves
`CupidaAgent::baseInstructions()` is in code and changes with a deploy: it is what keeps the recommendation honest. `CupidaSettings::$extra_instructions` is what the bookseller edits from the header action, appended and announced as coming from the shop. Never move the baseline into the settings.

## Anything a bookseller edits is a setting, not a table
`App\Settings\CupidaSettings` (spatie/laravel-settings) holds the extra instructions, the credit balance and its top-up date. There is no `cupida_prompts` or `cupida_credits` table and there should not be one: each of these is a single value with no rows, no history and no relations, and a table apiece is a model, a migration and a `firstOrCreate()` standing in for a property. Add a property and a line in `database/settings/`.

Resolve it with `app(CupidaSettings::class)` at the point of use rather than injecting it into a constructor — a bookseller who saves the modal expects the next swipe to use it. Values only reach the DB on `->save()`.

**A non-scalar setting must not carry a docblock.** `PropertyReflector::resolveType()` reads the native type only when a property has no doc comment at all; give one a `/**` and it looks for `@var`, resolves nothing when there is none, and builds no cast — so the raw string out of the JSON column is assigned to a typed property and fails as a TypeError on read, nowhere near the change that caused it. Writing `@var` instead works until Pint removes it as superfluous, which it does. Use a `/* */` block comment for the prose, as `$credit_topped_up_at` does, and type it concretely (`CarbonImmutable`, not `DateTimeInterface`) — the cast has to construct what it returns. `CupidaCreditTest` guards this.

`CupidaBudget` does the counting, not the settings class: a sum over `cupida_recommendations` is not one of the values a person edits.

Where the credit warnings are mailed is not a setting either, and was one: it is `site.admin_email`, out of `ADMIN_EMAIL`. The address belongs to whoever looks after the site rather than to a bookseller between top-ups, so it changes with the deploy environment and not from the Saldo IA modal -- which is why that modal now asks only for the number and the date. `WatchCupidaCredit::warningAddress()` falls back to `site.contact_email` when the variable is missing: a warning read by the wrong person gets acted on, one sent to an empty address does not.

## The recommendations list is cards, and every card touches two relations
`CupidaTable` is a `Stack`/`Split` layout in a `contentGrid(['lg' => 2])`, not rows: the cover is what makes a page of sessions readable, and a pitch is a paragraph a cell can only truncate. Two per row at most -- a third column turns cover, pitch and badges into columns of one word.

Each card draws `book.media` (our cover, and the link to our page for it) and `user`, so the table needs `->modifyQueryUsing(fn ($query) => $query->with(['user', 'book.media']))`. Without it a page is three queries a row; `CupidaAdminTest` counts them.

`likes`/`passes` render as badge lists off `likeLabels()`/`passLabels()`. The last round is covers, so a session carries a `book:<ean>` answer per swipe -- two dozen badges by the end. They resolve through `CupidaCatalog::titles()` (a memoized EAN => title map; `book()` scans and is for the single lookup), and both lists render in full -- no `limitList()`, no `tooltip()`: a cut list needs a hover to say what was cut, and there is no hover on a phone. Color is not what tells the two apart, an `icon()` is: a heart on every like badge, a cross on every pass.

Never `expandableLimitedList()` on a badge list: `TextColumn` only keeps the items past the limit in the markup when the column is *also* `listWithLineBreaks()` (one badge per line, which is what the card is trying to avoid). Without it the extras are sliced off before rendering, so the "show more" link renders, is clickable, and reveals nothing.

## The price is looked up by prefix, because an alias is not what answers
`cupida.model` is `claude-haiku-4-5`; the API answers with `claude-haiku-4-5-20251001`, and `Meta::$model` carries what answered. `PromptCost::rates()` therefore tries the exact key and then the longest `cupida.prices` key the id starts with. Keep the rate list keyed by alias, and never go back to a plain `config("cupida.prices.{$model}")` -- it prices every faked test and no real call.

## The EAN on a card is the shop's own page for the book
`CupidaRecommendation::shopUrl()` builds /libros/{ean}/{slug}/ from `CupidaCatalog::slugs()`. The slug lives only in the scraped pool, so a book the shop has dropped gets no link rather than a guessed address -- the page it would point at has gone too. The title links to *our* page when the EAN is also a `books` row, with an arrow icon; the EAN always points at theirs.

## A recommendation cannot be edited or deleted, by anybody
`CupidaRecommendationPolicy` allows `viewAny`/`view` to booksellers and returns false for `create`, `update`, `delete` and `deleteAny`. The log is the only record of what the shop recommended and what it spent, so a row removed from it takes a question with it. `CupidaTable` therefore has no toolbar actions at all, which is also what keeps the bulk-select checkboxes off the cards.

Rows are still written by `RecommendBook`, which does it as the app and never asks this policy.

## Sorting a content-grid table is a filter, not a sortable column
`CupidaTable` has no `sortable()` column on purpose. Filament renders its own sort select above a `contentGrid` table for as long as one visible column answers `isSortable()`, and that select sits loose between the search bar and the first card; the ordering is asked for in the filter panel instead, as the `sort` SelectFilter.

Two traps in that filter. It must order the query from `baseQuery()`, never `query()`: a filter's `query()` callback runs inside `$query->where(fn ($query) => ...)`, and an `orderBy` on that nested builder is discarded with the group. And SelectFilter falls back to `where('sort', $value)` unless a query callback exists, so it carries a no-op `->query(fn (Builder $query): Builder => $query)`.

Filters are applied before sorting, so the filter's `orderBy` is the primary one and the table's `defaultSort('created_at', 'desc')` follows it as the tiebreaker. Blank state is therefore newest-first, which is what the placeholder names. The value comes from the browser, so match the column against a whitelist before it reaches `orderBy()`.

## One writer does not get to be the whole shortlist
A liked author is the heaviest weight in `CupidaShortlist`, so left alone the top of the list is that author's whole backlist. `withoutAuthors` drops a writer from the scoring entirely and `perAuthor` caps how many of one writer's books get through. Nothing in the app passes `withoutAuthors` today -- it was the covers round's -- and it is kept for whatever asks a second question off the same shortlist.

The model's thirty are capped at `cupida.shortlist_per_author` (3) per writer so three liked authors do not fill it with three backlists. Both the exclusion and the cap also apply to the nothing-scored fallback list, and a book with no author is never capped. Keep the exclusion in the shortlist (before scoring), not as a post-filter in the deck: a filter over the top 18 leaves too few once the liked authors are removed.

## The opening card's promise, and its arrow on a phone

`cupida.start.matches` holds whole phrases ("tu próxima cita", "tu próximo
flechazo"), never the bare noun with a shared article in `promise`: flechazo,
crush and match are masculine and cita is not, so one "tu próxima :match" is
wrong three times in four. The card's two lines are two keys (`lead`, then
`promise`) with a `<br />` between them in the view -- the break is the
sentence's own, and markup in a translated string would have to be echoed
unescaped. `Cupida::matchWord()` picks by `seed % count` rather than
`random_int`, so a Livewire re-render cannot reword a sentence mid-read -- and
the es and en lists have to stay the same length, because `Arr::dot` parity in
`TranslationsTest` counts a list's numeric keys like any other.

The card carries two controls for one `start` action: the block button
(`hidden wide:block`) and the bouncing chevron (`.cupida-nudge`, `wide:hidden`).
Phone-only is `wide:` (1000px), the same breakpoint as the book page's pinned
buy bar. Both are in the DOM at every width, which is why
`assertSee(__('cupida.start.button'))` still covers the opening card.

## The subject deck is the config, not themes.json
`CupidaCatalog::deckThemes()` builds the theme round from `cupida.deck.subjects` plus a count derived from the pool — it no longer reads themes.json's `deck` flag. A subject is added to the deck by editing the config alone: no `cupida:scrape` run, no network, no themes.json row. themes.json stays the scrape's record of the shop's tree.

The count on a card is the pool count, not the shop's total for the subject page (the shop stocks 4,931 under FB; the pool holds 536, and the shortlist can only offer the 536). `cupida.deck.min_books` drops a subject the pool is thin on, so the list can stay long and prune itself as stock moves. The fixture is 12 books, so `useCupidaFixture()` lowers the floor to 1.

`CupidaShortlist::matchesSubject()` matches downwards only (book code starts with card code). Do not restore the upward branch: 504 pool books carry the bare code X and 4,941 a two-letter one, so matching upwards made "Manga" (XAM) score every comic in the shop and collapsed every narrow card onto its parent. Dropping it changed 17 of the 18 original cards by nothing, and fixed JBSF, which had been scoring all 527 "Sociedad" books as Feminismos.

A narrow code only works because the broad one is still a card to catch books filed coarsely — keep both in the list.

## Nothing on this page is measured against the window
Every panel of La Cupida fits a phone screen, and not one measurement in the view says so. The page opts into `fits-viewport` on `x-layouts.shelf`, which swaps the shell's `min-h-dvh` for a real `h-dvh` below `wide:`; from there ordinary flexbox does the work. `min-h-0` on every link in the chain (`[data-cupida]`, each `<section>`/`<main>`) is what allows a child to be squeezed at all, and the element that gives way is whichever one can afford to:

- the mark on the opening and waiting panels is `min-h-0 object-contain` and nothing else. `flex: 0 1 auto` means it can lose height but never gain it, so a tall phone and a laptop get the natural size it was drawn at and a short window gets less; `object-contain` keeps it from being squashed on the way.
- the deck is `flex-1` with an `aspect-[3/4]` — see the next rule.
- the portrait inside a card, and the card's own box, likewise.

This replaced a version where roughly 35 measurements were `min(<px>, <n>svh)` with `wide:` twins, each `n` tuned by hand against a screenshot. It fitted, and it was wrong: the numbers encoded the copy, the fonts and the footer as they were on the day, nothing told you when they had gone stale, and the fitted sizes were smaller than the design at every width. If a panel stops fitting, look for the element that should be giving way and let it — do not reach for a cap.

Sizes that remain in the view are the sizes the page was designed at. What survives with a `wide:` twin is a difference in *layout* between the folded phone version and the desktop one — the cover's width and shadow, the deck's floor, the title's measure — never a height fit.

## The deck is sized from the room left over, not from a fraction of the window
The question band + card stack + buttons have to fit a phone screen without scrolling — the buttons are the control, and a control below the fold is not a control.

The stack is NOT capped with `svh`. That was tried and left cream all round the card: `main` is `flex-1`, so the slack it absorbs varies with how many lines the question wraps to. Instead the deck is given the leftover height directly:

- `main > [x-data]` is `flex min-h-0 flex-1 flex-col justify-center`
- inside it, `<div class="flex min-h-0 flex-1 flex-col items-center">` is the room
- `.cupida-stack` is `aspect-[3/4] w-auto min-h-0 flex-1 max-h-[560px] max-w-full`

A `flex-1` item in a **column** has a definite main size (height), and `aspect-ratio` transfers it to the width. Three traps: (1) `h-[calc(100%…)]` does not work — nothing above has a definite height (`min-h-dvh` is a minimum), so percentage heights resolve to `auto` and the aspect box collapses to 0; (2) in a **row** container the transfer does not happen — a stretched cross size gives width 0; (3) `items-center` is required — the default `stretch` sets the width itself and the ratio has nothing to say.

`max-h-[560px]` is the size the card was drawn at — a ceiling, not a fit. `wide:min-h-[560px]` is the same number as a floor, and it belongs to the half of the page that is a document: from `wide:` up the shell has no height, the question band is set at 5.6vw and can take most of a laptop window on its own, and a deck free to shrink there would shrink to nothing rather than let the page scroll as it always has.

Everything inside a card is set at its designed size; the portrait carries `min-h-0` so it, and not the type, gives way when the card is small.

`cupida.swipe.help` is `hidden wide:block`: half of it is about arrow keys, and it was the one line on the screen that could go without costing a control.

The result panel is the one that scrolls: it carries `overflow-y-auto` so it scrolls inside the shell rather than being cut off by it.

## The result panel folds, it does not shrink
The result used to be a centered poster on a phone — cover in the middle, title full width under it, then match line, pitch and controls each waiting their turn. Measured at 402x684 that needed 1240px of a 540px screen, and the reader had to scroll past the cover to find out whether the book was worth scrolling for.

It is now three grid areas (`.cupida-result` in `resources/css/cupida.css`), folded two ways rather than resized:

- phone: `'cover head' / 'body body'` — the cover is a thumbnail and the title stands beside it, so the two cost one band instead of two; everything that is prose runs full width below.
- `wide:`: `'cover head' / 'cover body'` — byte-for-byte the layout the desktop already had.

Keep the areas in the stylesheet. The two arrangements differ in shape, not in values, and a `wide:` twin for each of six grid properties is unreadable.

The thumbnail column is `clamp(76px, 24vw, 108px)` — a proportion of the width it stands in, with nothing measured against the height: this is the one panel that scrolls (`overflow-y-auto` inside the fixed shell), and the cover should not pay for prose that was always going to be long. `.cupida-result__head` is `align-self: center` on a phone (a two-line title pinned to the top of a taller thumbnail leaves the author adrift) and `start` from `wide:` up.

The pitch is `.cupida-pitch`, clamped to two lines with a `cupida.result.more` control that removes the clamp — Alpine adds `.cupida-pitch--open`, never the clamp itself, so a failure leaves a readable paragraph rather than a dead button. Unclamped from `wide:` up, where the control is not rendered.

The mobile panel is left-aligned throughout. It used to centre the head and left-align the body; a folded layout with two left edges reads as neither.

Verified: 402x684 and 402x874 fit with no scroll, opening the pitch costs 2px, and desktop is unchanged (cover 240 in its own column, 56px gap, head above body).

## The question is one line on a phone, and the footer is gone
Two more lines the deck took back below `wide:`. Measured at 402x684 the card went from 206x274 to 292x389.

The question heading's third `min()` term is `calc((100vw - 2*max(22px,5vw)) / 15.6)`: the column -- the window less the section's own `px-[clamp(22px,5vw,80px)]`, whose ceiling never binds below `wide:` -- over the longest question there is, 15.45em for "What do you want from the book?" set in Gloock. A question longer than that wraps rather than overflows, so measure a new one (`canvas.measureText` at `100px Gloock`) and raise the divisor if it is wider. `max-w-none` is part of it: the desktop's `14ch` cap would force a wrap however small the type got. Never `whitespace-nowrap` -- that puts the heading off the side of the screen and gives the page a horizontal scroll.

`<x-site-footer :on-phone="false">`, passed through `x-layouts.shelf`'s `:footer-on-phone`, is `hidden wide:block` and is for La Cupida alone: every panel of this page is measured to fill the window, so the one line of cream under it is room the cards want. `CupidaPageTest` pins both halves against the request form, which wears the same short footer at every width.

## `fits-viewport` is what lets a page fit a phone without measuring it
`x-layouts.shelf` takes `fits-viewport`. It swaps the shell's `min-h-dvh` for `h-dvh wide:h-auto wide:min-h-dvh`, and adds `min-h-0` to the slot wrapper.

Why it matters: a minimum height bounds nothing. Flexbox can only take space away from a child when the parent has a ceiling, so under `min-h-dvh` a panel taller than the window simply grows and the page scrolls, whatever its children were told they may give up — `min-h-0`, `flex-1`, `object-contain` all do nothing. Put a height on the shell and the same flex rules that already share out the slack start reclaiming it. La Cupida went from ~35 hand-fitted `min(px, n svh)` caps to none on the strength of this one word.

Opt-in, because it is only right for a page that is a screen rather than a document: the shelf, author and publisher pages are lists that must run past the fold. And only below `wide:` — a laptop has room for a heading set at 5.6vw *and* everything under it, and squeezing a document into a window that was never the constraint costs the design without buying anything.

A page that opts in owns the consequence: anything in it that can outgrow the window must say how it scrolls (`overflow-y-auto` on the panel), or it is cut off rather than scrolled. See `.ai/rules/cupida.md` for the worked example.
