<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Book;
use App\Models\Publisher;
use App\Support\CoverPalette;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BookController extends Controller
{
    /** How many books fit on the shelf before a reader has to ask for the next page. */
    private const int PER_PAGE = 24;

    /**
     * The shop window: everything we have put on the web, newest first.
     *
     * What the bookseller has flagged as recommended leads the grid; after
     * that it is by publication date. A record with no date is pushed to the
     * back explicitly, because a NULL sorts first in a descending order on
     * Postgres and last on SQLite, and the shelf should not depend on that.
     */
    public function index(): View
    {
        $books = $this->shelf()->paginate(self::PER_PAGE);

        return view('books.index', [
            'books'   => $books,
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
        $books = $this->shelf()
            ->whereJsonContains('author_slugs', $author)
            ->paginate(self::PER_PAGE);

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
            'books'     => $this->shelf()->whereBelongsTo($publisher)->paginate(self::PER_PAGE),
            'palette'   => CoverPalette::fromCover(null),
        ]);
    }

    /**
     * The shop window for one book: one page, painted in its own cover colour.
     *
     * A book that is not visible on the web is a 404 for everyone except an
     * administrator, who reaches this page from the "see it on the web" button
     * on the edit screen -- which is precisely when the record is not ready to
     * be published yet.
     */
    public function show(Book $book): View
    {
        abort_unless($book->is_active || $this->mayPreview(), 404);

        $book->load(['publisher.media', 'media']);

        return view('books.show', [
            'book'          => $book,
            'palette'       => CoverPalette::fromCover($book->cover_color),
            'alsoByAuthors' => $this->alsoByAuthors($book),
            'fromPublisher' => $this->fromPublisher($book),
        ]);
    }

    /**
     * Every listing is the same shelf, only filtered differently.
     *
     * @return Builder<Book>
     */
    private function shelf(): Builder
    {
        return Book::query()
            ->active()
            ->with('media')
            ->orderByDesc('is_featured')
            ->orderByRaw('published_on is null')
            ->orderByDesc('published_on')
            ->orderByDesc('id');
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
            foreach ($book->contributorNames('autor') as $name) {
                if (Book::authorSlug($name) === $slug) {
                    return $name;
                }
            }
        }

        return str($slug)->headline()->value();
    }

    private function mayPreview(): bool
    {
        return auth()->user()?->role === UserRole::Admin;
    }

    /**
     * Other books on the shelf by any of the same people.
     *
     * Matched on the denormalized authors line rather than the contributors
     * JSON, so a co-written book is found from either name.
     *
     * @return Collection<int, Book>
     */
    private function alsoByAuthors(Book $book): Collection
    {
        $authors = $book->contributorNames('autor');

        if ($authors === []) {
            return new Collection;
        }

        return Book::query()
            ->active()
            ->whereKeyNot($book->getKey())
            ->where(function(Builder $query) use ($authors): void {
                foreach ($authors as $author) {
                    $query->orWhere('authors_line', 'like', '%' . $author . '%');
                }
            })
            ->orderByDesc('published_on')
            ->limit(4)
            ->get();
    }

    /**
     * What else we stock from the same imprint.
     *
     * @return Collection<int, Book>
     */
    private function fromPublisher(Book $book): Collection
    {
        if ($book->publisher_id === null) {
            return new Collection;
        }

        return Book::query()
            ->active()
            ->whereKeyNot($book->getKey())
            ->where('publisher_id', $book->publisher_id)
            ->orderByDesc('published_on')
            ->limit(3)
            ->get();
    }
}
