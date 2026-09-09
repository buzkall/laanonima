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
novelist. Measured over the whole 150-name pool it finds 118 faces; over a
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
`status: no_person` and `CupidaDeck::authorCards()` filters them **before** the
slice, so the pool stays 150 real writers deep rather than 145.

The recurring shop-ism is matched by `cupida.portraits.collective_patterns`
before any request is made, because it comes back under a new spelling every time
the catalog grows. A shared byline with a real Wikidata item is a judgment
call no pattern expresses: mark it with `--none`.

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

## The opening panel is measured in `svh`, below `wide:` only
La Cupida's `! $started` panel has to fit a phone screen without scrolling, and a phone never gives the page the whole window: on an iPhone 17 (402x874pt) the browser keeps ~190pt for its bars. Sized in px alone the card fitted 874 exactly and dropped the chevron off the bottom of the real device.

Every vertical measurement in that panel is therefore `min(<original px>, <n>svh)`: the section's `pt`/`pb`, the mark's `max-h`, both paragraph margins, the rule's `pt`, both type sizes, the chevron's margin and icon size. Verified to fit at 402x600, 402x684, 402x874 and 430x660.

`svh`, not `dvh` — the small viewport is the worst case (bars showing) and, unlike `dvh`, does not change while the reader scrolls, which would resize the type under their thumb.

Each cap has a `wide:` twin restoring the original px value, so the desktop card is byte-for-byte what it was. Do not drop those overrides: `32svh` on a 900px-tall laptop shrinks the mark from 388px to 288px.

`object-contain` on the mark is load-bearing: `max-h` on a replaced element whose width is set squashes it; contain letterboxes it at its own ratio instead.

## The deck is sized from the room left over, not from a fraction of the window
The question band + card stack + buttons have to fit a phone screen without scrolling — the buttons are the control, and a control below the fold is not a control.

The stack is NOT capped with `svh`. That was tried and left cream all round the card: `main` is `flex-1`, so the slack it absorbs varies with how many lines the question wraps to. Instead the deck is given the leftover height directly:

- `main > [x-data]` is `flex min-h-0 flex-1 flex-col justify-center`
- inside it, `<div class="flex min-h-0 flex-1 flex-col items-center">` is the room
- `.cupida-stack` is `aspect-[3/4] w-auto min-h-0 flex-1 max-h-[560px] max-w-full`

A `flex-1` item in a **column** has a definite main size (height), and `aspect-ratio` transfers it to the width. Three traps: (1) `h-[calc(100%…)]` does not work — nothing above has a definite height (`min-h-dvh` is a minimum), so percentage heights resolve to `auto` and the aspect box collapses to 0; (2) in a **row** container the transfer does not happen — a stretched cross size gives width 0; (3) `items-center` is required — the default `stretch` sets the width itself and the ratio has nothing to say.

`wide:min-h-[560px]` keeps a laptop's card at its original 420x560 and lets the panel run past the fold as it always has, rather than crushing the deck in a short window.

Everything inside a card (`p`, kicker, `h2`, note, portrait `max-h`) is `min(px,svh)`-capped with a `wide:` twin, because the card is now much smaller on a phone than it used to be.

`cupida.swipe.help` is `hidden wide:block`: half of it is about arrow keys, and it was the one line on the screen that could go without costing a control.

The result panel is deliberately NOT made to fit: measured at 402x684 it needs 1240px against 540 available, and most of that is the pitch and the title. It is a reading screen and it scrolls.
