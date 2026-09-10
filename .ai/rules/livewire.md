---
paths:
  - 'resources/views/cupida/index.blade.php,resources/views/livewire/cupida.blade.php'
---

# Livewire

## The swipe starts on the stack and ends on the window
`@pointerdown` belongs to `.cupida-stack`; `pointermove`, `pointerup` and `pointercancel` must stay `.window`-bound. `setPointerCapture()` is the only reason a pointer that has left the card still reports back to the stack, and the browser hands that capture back on its own -- Safari most often, and any browser the moment a morph replaces the capturing element. A `pointerup` bound to the stack is then delivered to the page behind the card instead, `release()` never runs, and the card sits tilted and stamped and undroppable until the arrow keys answer it. Nothing throws, so it reads as a random freeze.

Because the window hears every pointer, each handler checks `event.pointerId` against the one that started the drag. `pointercancel` springs back and never answers: it carries no intent. `drag()` also treats a mouse move with `buttons === 0` as the drop it never heard.
