<?php

use App\Actions\Books\FetchBookMetadata;
use App\Enums\BookLanguage;
use App\Support\BookMetadata\BookMetadataProvider;
use App\Support\BookMetadata\GoogleBooksProvider;
use App\Support\BookMetadata\OpenLibraryProvider;
use Illuminate\Support\Facades\Http;

const ISBN = '9788433920423';

beforeEach(function(): void {
    config()->set('books.metadata.google_books.key');
});

function fakeOpenLibraryHit(): void
{
    Http::fake([
        'openlibrary.org/api/books*' => Http::response(apiFixture('book-metadata/open-library-hit')),
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
        ->and($metadata->coverSourceUrl)->toStartWith('https://')
        ->and($metadata->source)->toBe('google_books');
});

it('falls through quietly when Google Books has exhausted its quota', function(): void {
    config()->set('books.metadata.google_books.key', 'test-key');
    Http::fake(['googleapis.com/*' => Http::response(apiFixture('book-metadata/google-books-quota-exceeded'), 429)]);

    expect(app(GoogleBooksProvider::class)->find(ISBN))->toBeNull();
});

it('merges the providers so one fills the gaps the other leaves', function(): void {
    config()->set('books.metadata.google_books.key', 'test-key');
    Http::fake([
        'openlibrary.org/api/books*' => Http::response(apiFixture('book-metadata/open-library-hit')),
        'covers.openlibrary.org/*'   => Http::response('', 404),
        'googleapis.com/*'           => Http::response(apiFixture('book-metadata/google-books-hit')),
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
    Http::fake(['openlibrary.org/*' => Http::response(apiFixture('book-metadata/open-library-miss'))]);
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
 | Open Library's "subjects" are reader-contributed tags, not a classification.
 | One real record (Momo, 9788420482767) yields "Girls", "Time", "tortoises",
 | "lilies", "interest" -- fifteen rows of noise in the Materias table.
 */
it('imports no subjects from Open Library', function(): void {
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
        'covers.openlibrary.org/*' => Http::response('', 404),
    ]);

    expect(app(FetchBookMetadata::class)('9788420482767')->subjects)->toBeEmpty();
});
