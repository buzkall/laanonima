<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Models\Publisher;
use App\Support\CoverPalette;
use App\Support\Og\OgCard;
use App\Support\Og\OgCardKey;
use App\Support\Og\OgCardStore;
use App\Support\ShelfArrangement;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;

class BookController extends Controller
{
    public function __construct(private readonly OgCardStore $shareCards = new OgCardStore) {}

    /**
     * The shop window: everything we have put on the web, in shelf order.
     */
    public function index(): View
    {
        $books = Book::query()->onShelf()->paginate($this->perPage());

        return view('books.index', [
            'books'   => $books,
            'palette' => CoverPalette::fromCover(null),
        ]);
    }

    /**
     * The same catalog, stood up on a shelf and drawn to scale.
     */
    public function shelf(): View
    {
        return view('books.shelf', [
            'arrangement' => ShelfArrangement::of(Book::query()->onStage()->get()),
            'palette'     => CoverPalette::fromCover(null),
        ]);
    }

    /**
     * Everything on the shelf one person had a hand in, whatever they did on
     * it: every name in "Quién lo escribe" is a link, translators included,
     * and they all lead here.
     *
     * Someone with nothing of theirs on the web is a 404 rather than an empty
     * shelf -- the last book they worked on has been withdrawn, or the
     * address was mistyped.
     */
    public function author(Author $author): View
    {
        $books = Book::query()->onShelf()
            ->whereHas('contributors', fn(Builder $query): Builder => $query->whereBelongsTo($author))
            ->paginate($this->perPage());

        abort_if($books->total() === 0, 404);

        return view('books.author', [
            'author'    => $author,
            'books'     => $books,
            'palette'   => CoverPalette::fromCover(null),
            'shareCard' => $this->shareCards->url(
                OgCardKey::forAuthor($author, $books->total(), $this->shelfStamp($author->books())),
                fn(): OgCard => OgCard::forAuthor($author, $books->total()),
            ),
        ]);
    }

    /**
     * Everything on the shelf from one imprint.
     *
     * A publisher with nothing visible on the web still has a page: unlike an
     * author, it is a record a bookseller curates, and an empty one means the
     * stock has gone rather than that the address is wrong.
     */
    public function publisher(Publisher $publisher): View
    {
        $publisher->load('media');

        $books = Book::query()->onShelf()->whereBelongsTo($publisher)->paginate($this->perPage());

        return view('books.publisher', [
            'publisher' => $publisher,
            'books'     => $books,
            'palette'   => CoverPalette::fromCover(null),
            'shareCard' => $this->shareCards->url(
                OgCardKey::forPublisher($publisher, $books->total(), $this->shelfStamp($publisher->books())),
                fn(): OgCard => OgCard::forPublisher($publisher, $books->total()),
            ),
        ]);
    }

    /**
     * The shop window for one book: one page, painted in its own cover color.
     *
     * Who may see it is `BookPolicy::view`, which 404s a book that is not on
     * the web yet for everyone but a bookseller.
     */
    public function show(Book $book): View
    {
        Gate::authorize('view', $book);

        $book->load(['publisher.media', 'media', 'contributors.author', 'authors']);

        return view('books.show', [
            'book'          => $book,
            'palette'       => CoverPalette::fromCover($book->cover_color),
            'alsoByAuthors' => $book->alsoByAuthors(),
            'fromPublisher' => $book->fromSamePublisher(),
            /* Only a book that is actually on the web gets a card. A bookseller
               previewing a draft shares without a picture rather than having
               its title and cover written to a public path before it is out. */
            'shareCard' => $book->is_active
                ? $this->shareCards->url(OgCardKey::forBook($book), fn(): OgCard => OgCard::forBook($book))
                : null,
        ]);
    }

    /**
     * How many books fit on the shelf before a reader has to ask for the next
     * page. The same length on every listing, from config/site.php.
     */
    private function perPage(): int
    {
        return (int)config('site.shelf.per_page');
    }

    /**
     * When anything on a shelf last moved, so a card that shows three covers is
     * redrawn once one of them changes.
     *
     * Deliberately one aggregate rather than a listener per book: a shelf card
     * depends on rows that have no idea it exists, and hanging invalidation off
     * every one of them is how SyncCoverColor's docblock says this goes wrong.
     *
     * @param  Relation<Book, *, *>  $books
     */
    private function shelfStamp(Relation $books): ?string
    {
        return $books->getQuery()->clone()->active()->max('books.updated_at');
    }
}
