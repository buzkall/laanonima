<?php

use App\Actions\Books\FetchBookMetadata;
use App\Enums\BookLanguage;
use App\Support\BookMetadata\BookMetadataProvider;
use App\Support\BookMetadata\CasaDelLibroProvider;
use App\Support\BookMetadata\GoogleBooksProvider;
use App\Support\BookMetadata\OpenLibraryProvider;
use App\Support\BookMetadata\PhysicalMeasure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

const ISBN = '9788433920423';

beforeEach(function(): void {
    config()->set('books.metadata.google_books.key');
});

function fakeOpenLibraryHit(): void
{
    Http::fake([
        'openlibrary.org/api/books*' => Http::response(apiFixture('book-metadata/open-library-hit')),
        'openlibrary.org/isbn/*'     => Http::response(apiFixture('book-metadata/open-library-edition')),
        'covers.openlibrary.org/*'   => Http::response('', 404),
    ]);
}

it('maps an Open Library record onto book columns', function(): void {
    fakeOpenLibraryHit();

    $metadata = app(OpenLibraryProvider::class)->find(ISBN);

    expect($metadata)->not->toBeNull()
        ->and($metadata->title)->toBe('La conjura de los necios')
        ->and($metadata->publisherName)->toBe('Anagrama')
        ->and($metadata->pages)->toBe(365)
        ->and($metadata->publishedYear)->toBe(2002)
        ->and($metadata->language)->toBe(BookLanguage::Spa)
        ->and($metadata->source)->toBe('open_library')
        ->and($metadata->contributors)->toBe([['name' => 'John Kennedy Toole', 'role' => 'author']]);
});

it('picks up the depósito legal that Open Library files under identifiers', function(): void {
    fakeOpenLibraryHit();

    expect(app(OpenLibraryProvider::class)->find(ISBN)->legalDeposit)->toBe('B. 46240-2002');
});

it('measures a book off the Open Library edition record', function(): void {
    fakeOpenLibraryHit();

    $metadata = app(OpenLibraryProvider::class)->find(ISBN);

    /* 8.3 x 5.4 x 1.2 inches, and 1.1 pounds. */
    expect($metadata->heightMm)->toBe(211)
        ->and($metadata->widthMm)->toBe(137)
        ->and($metadata->thicknessMm)->toBe(30)
        ->and($metadata->weightGrams)->toBe(499);
});

it('still returns the record when the edition has no measurements', function(): void {
    Http::fake([
        'openlibrary.org/api/books*' => Http::response(apiFixture('book-metadata/open-library-hit')),
        'openlibrary.org/isbn/*'     => Http::response('', 404),
        'covers.openlibrary.org/*'   => Http::response('', 404),
    ]);

    $metadata = app(OpenLibraryProvider::class)->find(ISBN);

    expect($metadata->title)->toBe('La conjura de los necios')
        ->and($metadata->heightMm)->toBeNull()
        ->and($metadata->weightGrams)->toBeNull();
});

it('treats an empty Open Library response as a miss, not an error', function(): void {
    Http::fake(['openlibrary.org/*' => Http::response(apiFixture('book-metadata/open-library-miss'))]);

    expect(app(OpenLibraryProvider::class)->find(ISBN))->toBeNull();
});

it('survives Open Library being unreachable', function(): void {
    Http::fake(['openlibrary.org/*' => Http::response('', 500)]);

    expect(app(OpenLibraryProvider::class)->find(ISBN))->toBeNull();
});

it('does not call Google Books without an API key', function(): void {
    Http::fake();

    expect(app(GoogleBooksProvider::class)->find(ISBN))->toBeNull();

    Http::assertNothingSent();
});

it('maps a Google Books volume once a key is configured', function(): void {
    config()->set('books.metadata.google_books.key', 'test-key');
    Http::fake(['googleapis.com/*' => Http::response(apiFixture('book-metadata/google-books-hit'))]);

    $metadata = app(GoogleBooksProvider::class)->find(ISBN);

    expect($metadata->title)->toBe('La conjura de los necios')
        ->and($metadata->subtitle)->toBe('Edición conmemorativa')
        ->and($metadata->isbn10)->toBe('8433920421')
        ->and($metadata->pages)->toBe(380)
        ->and($metadata->publishedOn)->toBe('2002-05-01')
        ->and($metadata->language)->toBe(BookLanguage::Spa)
        ->and($metadata->source)->toBe('google_books')
        /* Google offers 128px thumbnails and nothing else for a record like
           this one, which is not a cover -- and for a record it has no cover
           for at all, the same URL renders "image not available". */
        ->and($metadata->coverSourceUrl)->toBeNull();
});

it('reads the labeled dimensions Google Books files per side', function(): void {
    config()->set('books.metadata.google_books.key', 'test-key');
    Http::fake(['googleapis.com/*' => Http::response(apiFixture('book-metadata/google-books-hit'))]);

    $metadata = app(GoogleBooksProvider::class)->find(ISBN);

    expect($metadata->heightMm)->toBe(230)
        ->and($metadata->widthMm)->toBe(155)
        ->and($metadata->thicknessMm)->toBe(25);
});

it('puts a book onto its longest side whichever order the measurements arrive in', function(): void {
    /* The same paperback, written width-first and height-first. Open Library's
       free-text field holds both, so neither order may be assumed. */
    expect(PhysicalMeasure::dimensionsInMm('14 x 21 x 2 centimeters'))
        ->toBe(['height' => 210, 'width' => 140, 'thickness' => 20])
        ->and(PhysicalMeasure::dimensionsInMm('21 x 14 x 2 centimeters'))
        ->toBe(['height' => 210, 'width' => 140, 'thickness' => 20]);
});

it('refuses a measurement with no unit written on it', function(): void {
    expect(PhysicalMeasure::dimensionsInMm('21 x 14 x 2'))->toBeNull()
        ->and(PhysicalMeasure::lengthInMm('23.00'))->toBeNull();
});

it('throws away measurements no book could have', function(): void {
    expect(PhysicalMeasure::dimensionsInMm('0.2 x 0.1 x 0.01 inches'))->toBeNull()
        ->and(PhysicalMeasure::weightInGrams('4 grams'))->toBeNull();
});

it('falls through quietly when Google Books has exhausted its quota', function(): void {
    config()->set('books.metadata.google_books.key', 'test-key');
    Http::fake(['googleapis.com/*' => Http::response(apiFixture('book-metadata/google-books-quota-exceeded'), 429)]);

    expect(app(GoogleBooksProvider::class)->find(ISBN))->toBeNull();
});

it('merges the providers so one fills the gaps the other leaves', function(): void {
    config()->set('books.metadata.google_books.key', 'test-key');
    Http::fake([
        'openlibrary.org/api/books*'   => Http::response(apiFixture('book-metadata/open-library-hit')),
        'covers.openlibrary.org/*'     => Http::response('', 404),
        'googleapis.com/*'             => Http::response(apiFixture('book-metadata/google-books-hit')),
        'imagessl*.casadellibro.com/*' => Http::response('', 404),
    ]);

    $metadata = app(BookMetadataProvider::class)->find(ISBN);

    // Open Library is consulted first, so it wins on the fields both supply.
    expect($metadata->pages)->toBe(365)
        ->and($metadata->publisherName)->toBe('Anagrama')
        // ...and Google fills what Open Library had nothing for.
        ->and($metadata->subtitle)->toBe('Edición conmemorativa')
        ->and($metadata->isbn10)->toBe('8433920421')
        ->and($metadata->synopsis)->toStartWith('Ignatius J. Reilly')
        ->and($metadata->source)->toBe('open_library+google_books');
});

it('caches a hit so a second lookup makes no request', function(): void {
    fakeOpenLibraryHit();
    $fetch = app(FetchBookMetadata::class);

    expect($fetch(ISBN)->title)->toBe('La conjura de los necios');

    Http::fake();

    expect($fetch('978-84-339-2042-3')->title)->toBe('La conjura de los necios');
    Http::assertNothingSent();
});

it('caches a miss too, so an unknown Spanish ISBN is not looked up twice', function(): void {
    Http::fake([
        'openlibrary.org/*'            => Http::response(apiFixture('book-metadata/open-library-miss')),
        'imagessl*.casadellibro.com/*' => Http::response('', 404),
    ]);
    $fetch = app(FetchBookMetadata::class);

    expect($fetch('9788401352836'))->toBeNull();

    Http::fake();

    expect($fetch('9788401352836'))->toBeNull();
    Http::assertNothingSent();
});

it('does not reach the network for an ISBN that cannot be valid', function(): void {
    Http::fake();

    expect(app(FetchBookMetadata::class)('9788433920424'))->toBeNull();

    Http::assertNothingSent();
});

/*
 | Neither provider names a materia, and the DTO no longer carries one: Google
 | answers with free text and Open Library with reader-contributed tags -- one
 | real record (Momo, 9788420482767) yields "Girls", "tortoises", "lilies".
 | Which THEMA subject a book belongs to is a bookseller's judgment, made in
 | the panel. What the providers said survives in `raw_metadata`.
 */
it('leaves the materia to the bookseller and keeps what the provider said', function(): void {
    Http::fake([
        'openlibrary.org/api/books*' => Http::response([
            'ISBN:9788420482767' => [
                'title'    => 'Momo',
                'subjects' => [
                    ['name' => 'Girls'],
                    ['name' => 'tortoises'],
                    ['name' => 'lilies'],
                ],
            ],
        ]),
        'covers.openlibrary.org/*'     => Http::response('', 404),
        'imagessl*.casadellibro.com/*' => Http::response('', 404),
    ]);

    $metadata = app(FetchBookMetadata::class)('9788420482767');

    expect($metadata->toBookAttributes())->not->toHaveKey('subjects')
        ->and($metadata->raw)->toHaveKey('subjects');
});

/*
 | Casa del Libro. A cover and nothing else, last in the chain -- which is what
 | 9788433950857 (Anagrama, on the shelves and on nobody's free API) needed.
 */
it('derives a Casa del Libro cover URL from the ISBN itself', function(): void {
    Http::fake(['imagessl*.casadellibro.com/*' => Http::response('', 200)]);

    $metadata = app(CasaDelLibroProvider::class)->find('9788433950857');

    expect($metadata->coverSourceUrl)->toBe('https://imagessl3.casadellibro.com/a/l/t0/57/9788433950857.jpg')
        ->and($metadata->source)->toBe('casa_del_libro')
        ->and($metadata->title)->toBeNull();
});

it('reads a Casa del Libro 404 as no cover rather than a placeholder', function(): void {
    Http::fake(['imagessl*.casadellibro.com/*' => Http::response('', 404)]);

    expect(app(CasaDelLibroProvider::class)->find('9788433950857'))->toBeNull();
});

it('still finds a cover while Open Library is refusing connections', function(): void {
    Http::fake([
        'openlibrary.org/*'            => fn(): never => throw new ConnectionException('Connection refused'),
        'imagessl*.casadellibro.com/*' => Http::response('', 200),
    ]);

    $metadata = app(FetchBookMetadata::class)('9788433950857');

    expect($metadata->coverSourceUrl)->toContain('casadellibro.com')
        ->and($metadata->source)->toBe('casa_del_libro');
});

/*
 | A source that is down looks exactly like a source that has never heard of
 | the book, so a miss may not be cached for as long as a hit: the outage would
 | outlive itself by a day inside our own cache.
 */
it('looks a miss up again once the short miss TTL is out', function(): void {
    Http::fake([
        'openlibrary.org/*'            => fn(): never => throw new ConnectionException('Connection refused'),
        'imagessl*.casadellibro.com/*' => Http::sequence()->push('', 404)->push('', 200),
    ]);
    $fetch = app(FetchBookMetadata::class);

    expect($fetch('9788433950857'))->toBeNull();

    $this->travel(config('books.metadata.miss_cache_ttl') + 1)->seconds();

    expect($fetch('9788433950857')?->coverSourceUrl)->toContain('casadellibro.com');
});

it('takes a Google Books cover only in a size that is really one', function(): void {
    config()->set('books.metadata.google_books.key', 'test-key');
    Http::fake(['googleapis.com/*' => Http::response(['items' => [['volumeInfo' => [
        'title'      => 'La conjura de los necios',
        'imageLinks' => [
            'smallThumbnail' => 'http://books.google.com/books/content?id=0FUv&zoom=5',
            'thumbnail'      => 'http://books.google.com/books/content?id=0FUv&zoom=1',
            'large'          => 'http://books.google.com/books/content?id=0FUv&zoom=3',
        ],
    ]]]])]);

    expect(app(GoogleBooksProvider::class)->find(ISBN)->coverSourceUrl)
        ->toBe('https://books.google.com/books/content?id=0FUv&zoom=3');
});

/*
 | Casa del Libro is asked before Google Books, so the cover on a Spanish book
 | is a cover and not a 128px thumbnail. Google still fills the words.
 */
it('prefers a Spanish cover over what Google Books calls one', function(): void {
    config()->set('books.metadata.google_books.key', 'test-key');
    Http::fake([
        'openlibrary.org/*'            => Http::response(apiFixture('book-metadata/open-library-miss')),
        'imagessl*.casadellibro.com/*' => Http::response('', 200),
        'googleapis.com/*'             => Http::response(apiFixture('book-metadata/google-books-hit')),
    ]);

    $metadata = app(BookMetadataProvider::class)->find(ISBN);

    expect($metadata->coverSourceUrl)->toContain('casadellibro.com')
        ->and($metadata->title)->toBe('La conjura de los necios')
        ->and($metadata->source)->toBe('casa_del_libro+google_books');
});
