---
paths:
  - 'resources/css/cupida.css,resources/views/livewire/cupida.blade.php'
---

# Views Livewire

## Unlayered rules in cupida.css beat Tailwind utilities
`cupida.css` is imported unlayered, and an unlayered rule outranks anything in Tailwind's `utilities` layer regardless of order. So a `.cupida-button` sized in the stylesheet cannot be resized with `size-*` or `w-*` on the element in the view -- the utility silently loses. Add a modifier class in the stylesheet instead (`.cupida-button--info` is the precedent). The same applies to any property `.cupida-card`, `.cupida-stack` or `.cupida-stamp` set there.
