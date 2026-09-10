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
  - app/Models/CupidaRecommendation.php
---

# Books

## Asking for a book is a form behind a sign-in, not a mailto
A `BookRequest` is a reader asking us to find a book, not a sale. An orders resource is coming and will be a separate thing, so never call one an "order" (`pedido`) in copy, labels or comments: it is a "solicitud de libro" in Spanish and a "book request" in English, everywhere.

Every call to action posts to `book_requests` now, the in-stock "guárdamelo" included: putting a copy aside is a note to the bookseller just as much as ordering one we do not have, and a mailto was a request nobody could follow up in the panel. One form serves every way in: `/pedir-libro` empty, `/libro/{book}/pedir` filled in from that book, with `book_id` on the row. Only the copy differs, so never fork the view -- a book with stock swaps the kicker and the intro for `book_requests.public.held_*`, which put a copy aside instead of promising to order one from the distributor.

Both routes are behind `auth`, and `bootstrap/app.php` sends a guest to `UserRole::Client->loginUrl()`. `user_id` is therefore required and the row carries no name, email or telephone: those are read off `users` through the relation, so correcting an address fixes every request that reader has open. The telephone is asked for on the form only while the account has none, and `BookRequestController::rememberPhone()` writes it to `users` -- a number already given is never overwritten from here.

`BookRequest` is a note for the bookseller, never a catalog record: `book_id` stays nullable and `nullOnDelete`, so withdrawing a book must not take the request with it.

Two resources over one model. The shop's (`App\Filament\Resources\BookRequests`) works every row. The reader's (`App\Filament\Client\Resources\BookRequests`) is one listing scoped in `getEloquentQuery()` -- not on the table, so a record reached by URL is out of reach too -- with no form and no edit page: a request is a message to the shop, and letting the sender rewrite it leaves the bookseller chasing a title that quietly changed. The only thing a reader may do is `WithdrawBookRequestAction`, gated by `BookRequestPolicy::withdraw` (own, and still open).

Mail to `site.contact_email` is sent inline (no queue worker in front of this site) with the reader's address as reply-to, both when a request arrives and when one is called off. Its panel link passes `panel: UserRole::Admin->panelId()` explicitly, because the withdrawal is sent from the client panel and an unqualified `getUrl()` resolves against whichever panel is current.

Once a request is in, the receipt takes the whole page: it lives in the colored band instead of the form, and the form's half of the page is not rendered at all. Two panels each said their own thing over the other and each offered its own way back to the shelf. `book_request_sent` therefore flashes the request's **id**, not its title -- `BookRequestController::create()` reads the row back (scoped to the reader, because an id is guessable) so the receipt can show the book's cover and wear its color. A request with no `book_id` still finds one through `Isbn::toIsbn13()` on what the reader typed, which is what makes a hand-typed ISBN off a back cover worth trying.

`x-site-footer` takes a `:cta` prop, passed down from `x-layouts.shelf` as `:footer-cta`, so the request page does not advertise itself.

## The ULID key is the shared page's only authorization
`cupida_recommendations.id` is a ULID, not a serial, and that is a security decision rather than a style one. `cupida.recommendation` publishes one row at `/la-cupida/recomendacion/{id}`, so a countable key would let anyone walk every reader's session — and the row carries `user_id`, `likes` and `passes`. Never swap it back, and never expose the row under any other addressable key.

`SharedRecommendationController` deliberately runs no gate. `CupidaRecommendationPolicy` answers `isBookseller()` and takes a non-null `User`, so putting the public page behind it would refuse every reader who was handed a link. Unguessability *is* the access control, which is also why the page must render nothing about the reader: the book and the writing about it, never `user`, `likes` or `passes`. `SharedRecommendationPageTest` pins that.

Replacing the key was contained because nothing points at this table — both its foreign keys point outwards, at `users` and `books`. That stops being true the moment something references a recommendation.
