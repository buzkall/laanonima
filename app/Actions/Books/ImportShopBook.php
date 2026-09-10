<?php

namespace App\Actions\Books;

use App\Enums\BookAvailability;
use App\Enums\ContributorRole;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\Subject;
use App\Support\Cupida\CupidaCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * File a book out of the scraped shop pool as one of ours.
 *
 * La Cupida recommends from the shop's catalog, which is far larger than this
 * site's, so most readers used to be handed off to the old site at the exact
 * moment the page had earned their attention. This is what lets the result
 * screen link to our own page instead: the pool entry already carries every
 * column that matters -- title, author, publisher, synopsis, price, THEMA
 * subject, pages, year -- so the row is written with no network call at all,
 * inside the request the reader is already spending on the model.
 *
 * Whatever the free ISBN sources can add on top (binding, measurements, a
 * cover) is `EnrichImportedBook`'s job, and it runs after the response because
 * it is three providers with a five-second timeout apiece.
 */
class ImportShopBook
{
    /**
     * How a record filed this way is recognized afterwards -- by a bookseller
     * reviewing what was catalogd automatically, and by the daily cap.
     */
    public const SOURCE = 'cupida-shop';

    /**
     * Is this EAN already one of ours?
     *
     * Deliberately without the `active()` scope that `Recommendation` uses. A
     * bookseller who un-publishes a book leaves a row that still owns the
     * unique `isbn13`, and a lookup that cannot see it would try to insert a
     * second one and fail inside a reader's request.
     *
     * Both columns, because the two are not the same thing for every record: a
     * book catalogd from an ISBN has `isbn13`, one catalogd from a barcode has
     * `ean13`, and the shop's number can be either.
     */
    public static function existing(string $ean): ?Book
    {
        return Book::query()
            ->where(fn($query) => $query->where('isbn13', $ean)->orWhere('ean13', $ean))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $entry  a row out of the scraped pool
     */
    public function __invoke(array $entry): ?Book
    {
        $ean = (string)($entry['ean'] ?? '');

        if ($ean === '' || blank($entry['title'] ?? null)) {
            return null;
        }

        $existing = self::existing($ean);

        if ($existing instanceof Book) {
            /* A book taken off the web on purpose stays off it. Returning it
               here would put a 404 behind the result screen's button. */
            return $existing->is_active ? $existing : null;
        }

        try {
            $book = Book::create($this->attributes($entry));
        } catch (UniqueConstraintViolationException) {
            /* Two readers recommended the same book at the same moment. The
               other request won; its row is the answer. */
            return self::existing($ean);
        }

        $contributors = $this->contributorsFor($entry);

        if ($contributors !== []) {
            $book->syncContributors($contributors);
        }

        return $book;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function attributes(array $entry): array
    {
        $ean = (string)$entry['ean'];
        $available = (bool)($entry['available'] ?? false);

        return array_filter([
            /* Both columns carry the EAN. `isbn13` is the required unique one
               and is what the panel and the metadata lookup work from; `ean13`
               is what makes the row findable by the number the shop uses, even
               for a barcode that is not a valid ISBN. */
            'isbn13' => $ean,
            'ean13'  => $ean,
            /* `slug` is left out on purpose: Book's saving hook derives it from
               the title and the ISBN. */
            'title'              => (string)$entry['title'],
            'publisher_id'       => $this->publisherIdFor($entry),
            'subject_id'         => $this->subjectIdFor((array)($entry['subjects'] ?? [])),
            'synopsis'           => $entry['synopsis'] ?? null,
            'pages'              => $entry['pages'] ?? null,
            'published_year'     => $entry['year'] ?? null,
            'price_cents'        => $entry['price_cents'] ?? null,
            'external_reference' => $this->shopUrl($entry),
            /* The pool says what the shop has on the shelf, and the book page
               decides its call to action on `stock`. Left at the column's zero
               a book we have just recommended would render "no lo tenemos" and
               offer the request form, which is the hand-off this whole change
               is removing. It is a placeholder a bookseller corrects, not a
               count of copies. */
            'stock'           => $available ? 1 : 0,
            'availability'    => ($available ? BookAvailability::Available : BookAvailability::OutOfStock)->value,
            'is_active'       => true,
            'metadata_source' => self::SOURCE,
        ], fn(mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function publisherIdFor(array $entry): ?int
    {
        $name = $entry['publisher'] ?? null;

        if (! is_string($name) || blank($name)) {
            return null;
        }

        return Publisher::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id;
    }

    /**
     * The most specific THEMA subject the shop filed the book under that we
     * actually have a row for.
     *
     * A code is its own path -- FMM sits under FM sits under F -- so a code
     * with no row of its own still says which shelf the book belongs on. The
     * pool's codes come from the shop's own tree and ours from a seed of it,
     * and the two drift, so walking a code down its prefixes is what keeps a
     * book off the "sin materia" pile for the sake of one missing leaf.
     *
     * @param  array<int, mixed>  $codes
     */
    private function subjectIdFor(array $codes): ?int
    {
        $codes = array_filter(array_map(strval(...), $codes));

        /* Longest first: the most specific code the shop gave us is the one
           worth trying before any of its parents. */
        usort($codes, fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($codes as $code) {
            for ($length = strlen($code); $length > 0; $length--) {
                $subject = Subject::query()->where('code', substr($code, 0, $length))->first();

                if ($subject instanceof Subject) {
                    return $subject->id;
                }
            }
        }

        return null;
    }

    /**
     * The title page, as far as a listing knows it: one author, in the order
     * the shop writes them ("O'Farrell, Maggie").
     *
     * A collective byline is filed as nobody. "Vv. Aa." is not a person, and an
     * Author row for it would earn a page of its own at /autor/vv-aa.
     *
     * @param  array<string, mixed>  $entry
     * @return array<int, array{name: string, role: ContributorRole}>
     */
    private function contributorsFor(array $entry): array
    {
        $author = $entry['author'] ?? null;

        if (! is_string($author) || blank($author) || CupidaCatalog::isCollectiveName($author)) {
            return [];
        }

        return [[
            'name' => CupidaCatalog::readableName($author),
            'role' => ContributorRole::Author,
        ]];
    }

    /**
     * Where the book came from, kept so a bookseller reviewing the record can
     * see the listing it was filed off.
     *
     * @param  array<string, mixed>  $entry
     */
    private function shopUrl(array $entry): ?string
    {
        $slug = $entry['slug'] ?? null;

        if (! is_string($slug) || blank($slug)) {
            return null;
        }

        return rtrim((string)config('cupida.scrape.base_url'), '/') . "/libros/{$entry['ean']}/{$slug}/";
    }
}
