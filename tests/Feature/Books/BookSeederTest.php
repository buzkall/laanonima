<?php

use App\Models\Book;
use App\Models\Publisher;
use App\Support\Isbn;
use Database\Seeders\BookSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function() {
    Storage::fake('public');

    /*
     | Two paths to a cover, and both are exercised here: the four newest books
     | name their source in books.json because no free API has them, while the
     | rest fall back to a provider lookup. The Open Library fake echoes back
     | whichever ISBN was asked for, so that second path resolves too.
     */
    Http::fake([
        'openlibrary.org/api/books*' => function($request) {
            parse_str((string)parse_url($request->url(), PHP_URL_QUERY), $query);
            $isbn = str_replace('ISBN:', '', $query['bibkeys'] ?? '');

            return Http::response([
                "ISBN:{$isbn}" => [
                    'title' => 'Cualquier título',
                    'cover' => ['large' => "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg"],
                ],
            ]);
        },
        '*' => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg']),
    ]);
});

it('seeds the whole hand-curated catalogue', function() {
    $this->seed(BookSeeder::class);

    // Temas de Hoy publishes two of them, hence seven publishers for eight books.
    expect(Book::count())->toBe(8)
        ->and(Publisher::count())->toBe(7);
});

it('carries a check-digit-valid ISBN for every seeded book', function() {
    $this->seed(BookSeeder::class);

    Book::pluck('isbn13')->each(
        fn(string $isbn) => expect(Isbn::isValid($isbn))->toBeTrue("{$isbn} is not a valid ISBN-13"),
    );
});

it('gives every seeded book a real price, a publisher and a synopsis', function() {
    $this->seed(BookSeeder::class);

    Book::with('publisher')->get()->each(function(Book $book) {
        expect($book->price_cents)->toBeGreaterThan(0, "{$book->title} has no price")
            ->and($book->publisher)->not->toBeNull("{$book->title} has no publisher")
            ->and($book->synopsis)->not->toBeEmpty("{$book->title} has no synopsis");
    });
});

it('records translators alongside authors', function() {
    $this->seed(BookSeeder::class);

    $schwarzenbach = Book::firstWhere('isbn13', '9788495587176');

    expect($schwarzenbach->title)->toBe('Muerte en Persia')
        ->and($schwarzenbach->authors_line)->toBe('Annemarie Schwarzenbach')
        ->and($schwarzenbach->contributorNames('traductor'))
        ->toBe(['Richard Gross', 'María Esperanza Romero'])
        ->and($schwarzenbach->original_language->value)->toBe('deu')
        ->and($schwarzenbach->original_title)->toBe('Tod in Persien');
});

it('joins co-authors into one authors line', function() {
    $this->seed(BookSeeder::class);

    expect(Book::firstWhere('isbn13', '9791387748586')->authors_line)
        ->toBe('Ana Garriga, Carmen Urbita');
});

it('downloads a cover for every book, including the ones no free API knows', function() {
    $this->seed(BookSeeder::class);

    Book::all()->each(function(Book $book) {
        expect($book->cover_path)->not->toBeNull("{$book->title} has no cover");
        Storage::disk('public')->assertExists($book->cover_path);
    });
});

it('can be run twice without duplicating anything', function() {
    $this->seed(BookSeeder::class);
    $this->seed(BookSeeder::class);

    expect(Book::count())->toBe(8)
        ->and(Publisher::count())->toBe(7);
});

it('seeds books with a slug when run through the default seeder', function() {
    $this->seed();

    expect(Book::count())->toBe(8);

    Book::all()->each(fn(Book $book) => expect($book->slug)->not->toBeEmpty(
        "{$book->title} has no slug",
    ));
});

it('reuses a cover already on disk instead of downloading it again', function() {
    Storage::disk('public')->put('covers/9788419812742.jpg', 'ya descargada');

    Http::fake([
        'openlibrary.org/api/books*' => Http::response([], 500),
        '*'                          => Http::response([], 500),
    ]);

    $this->seed(BookSeeder::class);

    expect(Book::firstWhere('isbn13', '9788419812742')->cover_path)
        ->toBe('covers/9788419812742.jpg')
        ->and(Storage::disk('public')->get('covers/9788419812742.jpg'))
        ->toBe('ya descargada');
});

it('leaves a book without a cover when every source is unreachable', function() {
    Http::fake(fn() => throw new ConnectionException('Connection reset by peer'));

    $this->seed(BookSeeder::class);

    expect(Book::count())->toBe(8)
        ->and(Book::whereNotNull('cover_path')->count())->toBe(0);
});

it('needs no metadata provider to cover the catalogue', function() {
    Http::fake([
        'openlibrary.org/*'    => Http::response([], 500),
        'www.googleapis.com/*' => Http::response([], 500),
        '*casadellibro.com/*'  => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $this->seed(BookSeeder::class);

    expect(Book::whereNull('cover_path')->count())->toBe(0);

    Http::assertNotSent(fn(Request $request) => str_contains($request->url(), 'openlibrary.org'));
});
