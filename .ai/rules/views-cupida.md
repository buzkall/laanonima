---
paths:
  - 'resources/views/components/site-footer.blade.php,resources/views/components/layouts/shelf.blade.php,resources/views/cupida/shared.blade.php'
---

# Views Cupida

## The footer's "pídenoslo" carries the book the page is about
`x-site-footer` takes a `:book`, passed down from `x-layouts.shelf` as `:footer-book`, and it decides only where the call to action lands: `/libro/{book}/pedir` with one, `/pedir-libro` without.

A shared recommendation passes `$recommendation->book` — the footer is the only route from that page to the form, and the page is about one book, so an empty form there asks the reader to type back what we already know. It is null when La Cupida found the book in the shop's pool but we hold no `books` row: nothing to fill in from, and the empty form still takes the request.

The book page does NOT go through this — `books/show.blade.php` has its own footer and already builds `$orderUrl` itself.
