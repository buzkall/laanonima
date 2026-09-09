---
paths:
  - 'resources/views/books/shelf.blade.php,resources/js/shelf.js,resources/css/shelf.css'
---

# Css

## The shelf's peek card lives outside the scroller
`.shelf__scroll` sets `overflow-y: hidden` (it has to, or the horizontal scroller grows a vertical one), so anything drawn above a book inside it is cut off. The peek card therefore hangs off `.shelf` itself and `mountPeek()` measures against that frame: a viewport rectangle already carries the horizontal scroll, so no `scrollLeft` term. It is then held inside the frame — clamped `PEEK_EDGE` from either end, and never higher than its own height above the shelf top, so a tall book gets the card over its cover rather than off the page.
