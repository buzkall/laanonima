---
paths:
  - 'resources/views/books/shelf.blade.php,resources/js/shelf.js,resources/css/shelf.css,app/Support/ShelfBook.php'
---

# Support

## The shelf at /estanteria is drawn from millimetres
Blade renders every book: a real `<a>` in a real `<li>`, carrying `--mm-w/--mm-h/--mm-d` as *unitless* numbers. CSS turns them into pixels through one `--shelf-scale`, so a resize rewrites one property rather than four per book. `--shelf-scale` is declared on `.shelf`, so the script must write its override onto the stage (a descendant) or the declaration wins.

`ShelfBook` settles the three measurements: read off the record when the ISBN lookup found them, otherwise estimated from binding + pages via `site.shelf.sizes`. Both long sides or neither -- half a record stands a book at the wrong proportions. `isMeasured` says which it was; never present an estimate as a measurement.

The row is shuffled per visit (`Book::onStage()`) and `BookController::turnSomeFaceOut()` turns `site.shelf.facing_out` of them cover-first, picked by position so they are not all at the left end. `is_featured` deliberately has no say on this page: a shelf that always led with the same books is a list with pictures. Facing out is therefore a property of the `ShelfBook`, not of the `Book`.

An unmeasured book takes its *proportions* from its cover: `fitCoversToFaces()` rewrites `--mm-w` from the image's natural size before the first build, so only the height stays a guess. Without it a bad binding crops the cover -- `board_book` maps to a square and squashed an ordinary paperback. This is why the covers are not lazy: the physics needs their proportions before it lays the row out.

Two traps:
- The faces are `<span>`s, blockified only by `position: absolute`. Any rule that makes them static must set `display: block`, or width/height/aspect-ratio are ignored -- an `<img>` survives that, being replaced, and a book with no cover collapses to its text.
- Dragging is the only move that can strand a book: it stops colliding with its neighbours so it comes out cleanly, and released over the row it can get that collision back while overlapping two books, which the solver settles by standing it on top of them (~2.5% of drops). `settle()` therefore tidies before it freezes -- anything more than `STRAY_PX` above the board is lifted over its own slot and dropped back, twice at most. Do not replace this with a snap: the drop is what makes it read as the book falling into place.
- A hovered book needs `z-index`, not just rotation. Each book carries its own 3-D context, so the row composites in document order and a book turned towards the reader gets sliced by whatever stands to its right.
- Wait on an image's `load`, never `decode()`: the natural size is known once the header is parsed, while decoding is rendering work a browser defers indefinitely in a hidden tab.
- A background tab gets no animation frames while the settle timer keeps counting in wall-clock, which freezes books in mid-air. The physics waits for `visibilityState === 'visible'` before the first build, and restarts the settle countdown on `visibilitychange`. Keep both.

Matter.js is dynamically imported so its 84KB only reaches this page, and a failed import leaves the static flex row -- which is also what a reader with no JavaScript gets.
