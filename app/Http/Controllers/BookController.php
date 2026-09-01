<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Publisher;
use App\Support\CoverPalette;
use App\Support\ShelfBook;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class BookController extends Controller
{
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
     * The same catalogue, stood up on a shelf and drawn to scale.
     *
     * Not paginated: the shelf is one row a reader scrolls along, and every
     * book on it is a rigid body in a physics loop, so what limits it is the
     * row rather than the page. `site.shelf.on_stage` is the ceiling.
     */
    public function shelf(): View
    {
        $books = Book::query()->onStage()->limit((int)config('site.shelf.on_stage'))->get();

        return view('books.shelf', [
            'shelved' => $this->turnSomeFaceOut($books),
            'palette' => CoverPalette::fromCover(null),
        ]);
    }

    /**
     * Everything on the shelf by one person.
     *
     * There is no authors table: the slug in the address is the key, matched
     * against the denormalized `author_slugs` the model writes on every save.
     * An author we have nothing by is a 404 rather than an empty shelf -- the
     * address was either mistyped or the last book by them has been withdrawn.
     */
    public function author(string $author): View
    {
        $books = Book::query()->onShelf()
            ->whereJsonContains('author_slugs', $author)
            ->paginate($this->perPage());

        abort_if($books->total() === 0, 404);

        return view('books.author', [
            'name'    => $this->authorName($books, $author),
            'books'   => $books,
            'palette' => CoverPalette::fromCover(null),
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

        return view('books.publisher', [
            'publisher' => $publisher,
            'books'     => Book::query()->onShelf()->whereBelongsTo($publisher)->paginate($this->perPage()),
            'palette'   => CoverPalette::fromCover(null),
        ]);
    }

    /**
     * The shop window for one book: one page, painted in its own cover colour.
     *
     * Who may see it is `BookPolicy::view`, which 404s a book that is not on
     * the web yet for everyone but a bookseller.
     */
    public function show(Book $book): View
    {
        Gate::authorize('view', $book);

        $book->load(['publisher.media', 'media']);

        return view('books.show', [
            'book'          => $book,
            'palette'       => CoverPalette::fromCover($book->cover_color),
            'alsoByAuthors' => $book->alsoByAuthors(),
            'fromPublisher' => $book->fromSamePublisher(),
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
     * Turn a couple of the books cover-first, the way a bookseller does.
     *
     * Picked by position rather than off the front of the row, so the two that
     * face out are somewhere along the shelf instead of always at its left
     * end. `is_featured` deliberately has no say: the row is already shuffled,
     * so a shelf that always turned the same two books round would look less
     * like a shelf and more like a list with pictures.
     *
     * @param  EloquentCollection<int, Book>  $books
     * @return Collection<int, ShelfBook>
     */
    private function turnSomeFaceOut(EloquentCollection $books): Collection
    {
        $facingOut = $books->keys()
            ->shuffle()
            ->take((int)config('site.shelf.facing_out'))
            ->all();

        return $books->map(
            fn(Book $book, int $position): ShelfBook => ShelfBook::from(
                $book,
                in_array($position, $facingOut, true),
            ),
        );
    }

    /**
     * The author's name as it is written on the books, read back off the shelf.
     *
     * The slug is all the address carries, and slugging is lossy, so the name
     * has to come from a record rather than from the URL. Every book on the
     * page has this author, so the first one that slugs to it answers.
     *
     * @param  LengthAwarePaginator<int, Book>  $books
     */
    private function authorName(LengthAwarePaginator $books, string $slug): string
    {
        foreach ($books->items() as $book) {
            foreach ($book->contributorNames('author') as $name) {
                if (Book::authorSlug($name) === $slug) {
                    return $name;
                }
            }
        }

        return str($slug)->headline()->value();
    }
}
