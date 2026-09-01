---
paths:
  - 'resources/views/books/shelf.blade.php,resources/js/shelf.js,resources/css/shelf.css,app/Support/ShelfBook.php'
---

# Support

## The shelf at /estanteria is drawn from millimetres
Blade renders every book: a real `<a>` in a real `<li>`, carrying `--mm-w/--mm-h/--mm-d` as *unitless* numbers. CSS turns them into pixels through one `--shelf-scale`, so a resize rewrites one property rather than four per book. `--shelf-scale` is declared on `.shelf`, so the script must write its override onto the stage (a descendant) or the declaration wins.

`ShelfBook` settles the three measurements: read off the record when the ISBN lookup found them, otherwise estimated from binding + pages via `site.shelf.sizes`. Both long sides or neither -- half a record stands a book at the wrong proportions. `isMeasured` says which it was; never present an estimate as a measurement.

`ShelfArrangement::of($books, $seed)` decides the whole row from one seed, written to the page as `data-seed` so a shelf that came out badly can be put back exactly. `Book::onShelf()` picks the stock; the arrangement only arranges it. `is_featured` deliberately has no say here -- a shelf that always led with the same books is a list with pictures -- so facing out and lying flat are properties of the `ShelfBook`, not of the `Book`.

The row is built as **slots**, not books: a slot is one standing book or one pile. Piles (`site.shelf.stacks`) hold 2-3 books lying flat, biggest footprint on the bottom, and only on a shelf of `stack_needs` or more. A pile is never dropped -- three books rained onto one place land as three books on the floor -- so its bodies are created at rest and friction holds them. Books facing out are picked from single-book slots only, never two adjacent (two covers side by side read as a mistake at the table).

The row fades in a slot at a time (`is-arriving` + `--arrive-delay`), opacity only -- the transform belongs to the physics loop. It is a CSS animation rather than a class the script removes on a frame, but **the class is still removed on a timer**: an animation does not advance in a hidden tab, so its filled `from` state would otherwise leave the whole shelf at zero opacity until someone looked. Timers still run there, throttled.

Hover turns a standing book in place; a piled one is **lifted off the pile first** (`--rise`), then turned. The box pivots about its own centre on an axis that is vertical for a standing book, but a book in a pile has been rolled a quarter turn, so that axis is horizontal and tilting it in place drops its near edge through whatever it is resting on. `--rise` is applied *before* the turn, so it is measured in the book element's own frame -- which for a book rolled a quarter turn points up the screen -- and it is in millimetres via `--shelf-scale`, like every other length here.

That turn is the only way a pile shows what it holds: a flat book's cover faces the ceiling and the camera barely looks down, so its front face renders **366 x 2 px** at rest. Lifted and turned to 50 degrees it is 375 x 157. Do not simply un-hide it.

A pile is re-sorted in `build()`, after `fitCoversToFaces()`, not trusted as the server sends it. The server orders by footprint too but can only use the sizes it has, and unmeasured books all estimate to the same size for their binding -- so a shelf of paperbacks ties on every book and the order is arbitrary until the covers give real proportions.

A book lying flat is just a spine-out book rolled a quarter turn, so the CSS needs nothing: the roll is the body's angle, which `sync()` writes as `--rot`. It must be **anticlockwise** -- `writing-mode: vertical-rl` has already turned the glyphs clockwise to read top-to-bottom, and a second clockwise turn leaves the title upside down.

The shelf shows only books with a cover (`Book::withCover()`). The grid on the home page is happy without one -- it sets the title over the book's own colour -- but a blank coloured board among real covers reads as a missing image rather than as a book. Shelf tests therefore need `Storage::fake('public')` and a real `addCoverFromString(fakeCover())`, or the page renders empty.

A spine-out book's cover is **not drawn**: edge-on it is 266px of picture in about 13 of screen, which the compositor resamples into a band of noise beside every spine. `visibility` is delayed by `--shelf-turn` on the way out so the cover does not blink away mid-turn, and instant on the way in, where the face is too narrow to see it arrive. Never remove that asymmetry.

An unmeasured book takes its *proportions* from its cover: `fitCoversToFaces()` rewrites `--mm-w` from the image's natural size before the first build, so only the height stays a guess. Without it a bad binding crops the cover -- `board_book` maps to a square and squashed an ordinary paperback. This is why the covers are not lazy: the physics needs their proportions before it lays the row out.

Two traps:
- The faces are `<span>`s, blockified only by `position: absolute`. Any rule that makes them static must set `display: block`, or width/height/aspect-ratio are ignored -- an `<img>` survives that, being replaced, and a book with no cover collapses to its text.
- **Nothing falls from above.** Every book is created at rest in its place; the physics is for what the reader does to the shelf, not for how it arrives. Dropping them in was measured at **155/300 shelves with a book fallen flat** -- a spine 25px wide and 400 tall topples from any knock, most often the first or last to land, having a neighbour on one side only. Dropping from 60px gives 11/300; set down, 0/300. Bookends make it *worse* (249/300): falling books land on those instead. Only `WARM_MS` of solver time is needed afterwards, to push out the overlap between books placed touching.
- Dragging is the only move that can now strand a book: it stops colliding with its neighbours so it comes out cleanly, and released over the row it can get that collision back while overlapping two books, which the solver settles by standing it on top of them. `settle()` therefore tidies before it freezes, twice at most. Two tests, not one: **too high** (above `restY` by `STRAY_PX`) and **too far over** (`MAX_TILT`). Height alone never catches a book knocked flat, because a fallen book rests *below* where it should be, not above. Do not replace the drop-back with a snap: the short fall is what makes it read as the book going back into place.
- A hovered book needs `z-index`, not just rotation. Each book carries its own 3-D context, so the row composites in document order and a book turned towards the reader gets sliced by whatever stands to its right.
- Wait on an image's `load`, never `decode()`: the natural size is known once the header is parsed, while decoding is rendering work a browser defers indefinitely in a hidden tab.
- A background tab gets no animation frames while the settle timer keeps counting in wall-clock, which freezes books in mid-air. The physics waits for `visibilityState === 'visible'` before the first build, and restarts the settle countdown on `visibilitychange`. Keep both.

Matter.js is dynamically imported so its 84KB only reaches this page, and a failed import leaves the static flex row -- which is also what a reader with no JavaScript gets.
