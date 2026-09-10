---
paths:
  - app/Http/Controllers/BookRequestController.php
  - app/Models/BookRequest.php
  - 'app/Filament/Resources/BookRequests/**'
  - 'app/Filament/Client/Resources/BookRequests/**'
  - app/Policies/BookRequestPolicy.php
  - app/Http/Requests/StoreBookRequest.php
  - 'app/Mail/**'
  - resources/views/books/request.blade.php
  - app/Policies/CupidaRecommendationPolicy.php
---

# Books

## Asking for a book is a form behind a sign-in, not a mailto
A `BookRequest` is a reader asking us to find a book, not a sale. An orders resource is coming and will be a separate thing, so never call one an "order" (`pedido`) in copy, labels or comments: it is a "solicitud de libro" in Spanish and a "book request" in English, everywhere.

Every call to action posts to `book_requests` now, the in-stock "guárdamelo" included: putting a copy aside is a note to the bookseller just as much as ordering one we do not have, and a mailto was a request nobody could follow up in the panel. One form serves every way in: `/pedir-libro` empty, `/libro/{book}/pedir` filled in from that book, with `book_id` on the row. Only the copy differs, so never fork the view -- a book with stock swaps the kicker and the intro for `book_requests.public.held_*`, which put a copy aside instead of promising to order one from the distributor.

Both routes are behind `auth`, and `bootstrap/app.php` sends a guest to `UserRole::Client->loginUrl()`. `user_id` is therefore required and the row carries no name, email or telephone: those are read off `users` through the relation, so correcting an address fixes every request that reader has open. The telephone is asked for on the form only while the account has none, and `BookRequestController::rememberPhone()` writes it to `users` -- a number already given is never overwritten from here.

`BookRequest` is a note for the bookseller, never a catalog record: `book_id` stays nullable and `nullOnDelete`, so withdrawing a book must not take the request with it.

Two resources over one model. The shop's (`App\Filament\Resources\BookRequests`) works every row. The reader's (`App\Filament\Client\Resources\BookRequests`) is one listing scoped in `getEloquentQuery()` -- not on the table, so a record reached by URL is out of reach too -- with no form and no edit page: a request is a message to the shop, and letting the sender rewrite it leaves the bookseller chasing a title that quietly changed. The only thing a reader may do is `WithdrawBookRequestAction`, gated by `BookRequestPolicy::withdraw` (own, and still open).

Mail to `site.contact_email` is sent inline (no queue worker in front of this site) with the reader's address as reply-to, both when a request arrives and when one is called off. Its panel link passes `panel: UserRole::Admin->panelId()` explicitly, because the withdrawal is sent from the client panel and an unqualified `getUrl()` resolves against whichever panel is current.

`x-site-footer` takes a `:cta` prop, passed down from `x-layouts.shelf` as `:footer-cta`, so the request page does not advertise itself.
