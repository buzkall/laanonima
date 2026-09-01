<?php

use App\Models\Book;
use Illuminate\Support\Facades\Http;

/* The Open Library fixtures answer for this ISBN and no other. */
const MEASURED_ISBN = '9788433920423';

function fakeEditionWithDimensions(): void
{
    Http::fake([
        'openlibrary.org/api/books*' => Http::response(apiFixture('book-metadata/open-library-hit')),
        'openlibrary.org/isbn/*'     => Http::response(apiFixture('book-metadata/open-library-edition')),
        'covers.openlibrary.org/*'   => Http::response('', 404),
    ]);
}

beforeEach(function(): void {
    config()->set('books.metadata.google_books.key');
});

it('writes down how big a book is', function(): void {
    fakeEditionWithDimensions();

    $book = Book::factory()->create([
        'isbn13'       => MEASURED_ISBN,
        'height_mm'    => null,
        'width_mm'     => null,
        'thickness_mm' => null,
        'weight_grams' => null,
    ]);

    $this->artisan('books:measure')->assertSuccessful();

    /* 8.3 x 5.4 x 1.2 inches, and 1.1 pounds. */
    expect($book->refresh())
        ->height_mm->toBe(211)
        ->width_mm->toBe(137)
        ->thickness_mm->toBe(30)
        ->weight_grams->toBe(499);
});

it('never writes over a measurement that is already on the record', function(): void {
    fakeEditionWithDimensions();

    $book = Book::factory()->create([
        'isbn13'       => MEASURED_ISBN,
        'height_mm'    => 204,
        'width_mm'     => 132,
        'thickness_mm' => null,
        'weight_grams' => null,
    ]);

    $this->artisan('books:measure')->assertSuccessful();

    /* The two the bookseller measured stand; only the gaps are filled. */
    expect($book->refresh())
        ->height_mm->toBe(204)
        ->width_mm->toBe(132)
        ->thickness_mm->toBe(30);
});

it('leaves a book alone when the sources cannot measure it', function(): void {
    Http::fake([
        'openlibrary.org/api/books*' => Http::response(apiFixture('book-metadata/open-library-hit')),
        'openlibrary.org/isbn/*'     => Http::response('', 404),
        'covers.openlibrary.org/*'   => Http::response('', 404),
    ]);

    $book = Book::factory()->create(['isbn13' => MEASURED_ISBN, 'height_mm' => null, 'width_mm' => null]);

    $this->artisan('books:measure')->assertSuccessful();

    expect($book->refresh())->height_mm->toBeNull()->width_mm->toBeNull();
});

it('says so when there is nothing left to measure', function(): void {
    Http::fake();

    Book::factory()->create([
        'height_mm'    => 210,
        'width_mm'     => 140,
        'thickness_mm' => 18,
        'weight_grams' => 300,
    ]);

    $this->artisan('books:measure')
        ->expectsOutputToContain('Every book is already measured.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('asks about a measured book only when told to', function(): void {
    fakeEditionWithDimensions();

    Book::factory()->create([
        'isbn13'       => MEASURED_ISBN,
        'height_mm'    => 210,
        'width_mm'     => 140,
        'thickness_mm' => 18,
        'weight_grams' => 300,
    ]);

    $this->artisan('books:measure', ['--all' => true])->assertSuccessful();

    Http::assertSentCount(2);
});
