---
paths:
  - 'resources/views/books/shelf.blade.php,resources/js/shelf.js,resources/css/shelf.css,app/Support/ShelfBook.php'
---

# Support

## The shelf at /estanteria is drawn from millimetres
Blade renders every book: a real `<a>` in a real `<li>`, carrying `--mm-w/--mm-h/--mm-d` as *unitless* numbers. CSS turns them into pixels through one `--shelf-scale`, so a resize rewrites one property rather than four per book. `--shelf-scale` is declared on `.shelf`, so the script must write its override onto the stage (a descendant) or the declaration wins.

`ShelfBook` settles the three measurements: read off the record when the ISBN lookup found them, otherwise estimated from binding + pages via `site.shelf.sizes`. Both long sides or neither -- half a record stands a book at the wrong proportions. `isMeasured` says which it was; never present an estimate as a measurement.

Two traps:
- The faces are `<span>`s, blockified in shelf mode only by `position: absolute`. Any rule that makes them static (list mode) must set `display: block`, or width/height/aspect-ratio are ignored -- an `<img>` survives that, being replaced, and a book with no cover collapses to its text.
- A background tab gets no animation frames while the settle timer keeps counting in wall-clock, which freezes books in mid-air. The physics waits for `visibilityState === 'visible'` before the first build, and restarts the settle countdown on `visibilitychange`. Keep both.

Matter.js is dynamically imported so its 84KB only reaches this page, and a failed import leaves the static flex row -- which is also what a reader with no JavaScript gets.
