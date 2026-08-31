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
---

# Books

## Asking for a book is a form behind a sign-in, not a mailto
A `BookRequest` is a reader asking us to find a book, not a sale. An orders resource is coming and will be a separate thing, so never call one an "order" (`pedido`) in copy, labels or comments: it is a "solicitud de libro" in Spanish and a "book request" in English, everywhere.

Every "pídenoslo" call to action posts to `book_requests` now. One form serves both ways in: `/pedir-libro` empty, `/libro/{book}/pedir` filled in from that book, with `book_id` on the row. Only the copy differs, so never fork the view. The in-stock "guárdamelo" is still a mailto (`books.public.in_stock.subject`) -- it is a reservation of a copy we hold, not a request for one we do not.

Both routes are behind `auth`, and `bootstrap/app.php` sends a guest to `UserRole::Client->loginUrl()`. `user_id` is therefore required and the row carries no name, email or telephone: those are read off `users` through the relation, so correcting an address fixes every request that reader has open. The telephone is asked for on the form only while the account has none, and `BookRequestController::rememberPhone()` writes it to `users` -- a number already given is never overwritten from here.

`BookRequest` is a note for the bookseller, never a catalogue record: `book_id` stays nullable and `nullOnDelete`, so withdrawing a book must not take the request with it.

Two resources over one model. The shop's (`App\Filament\Resources\BookRequests`) works every row. The reader's (`App\Filament\Client\Resources\BookRequests`) is one listing scoped in `getEloquentQuery()` -- not on the table, so a record reached by URL is out of reach too -- with no form and no edit page: a request is a message to the shop, and letting the sender rewrite it leaves the bookseller chasing a title that quietly changed. The only thing a reader may do is `WithdrawBookRequestAction`, gated by `BookRequestPolicy::withdraw` (own, and still open).

Mail to `site.contact_email` is sent inline (no queue worker in front of this site) with the reader's address as reply-to, both when a request arrives and when one is called off. Its panel link passes `panel: UserRole::Admin->panelId()` explicitly, because the withdrawal is sent from the client panel and an unqualified `getUrl()` resolves against whichever panel is current.

`x-site-footer` takes a `:cta` prop, passed down from `x-layouts.shelf` as `:footer-cta`, so the request page does not advertise itself.
