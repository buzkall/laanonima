<?php

use App\Models\Book;
use App\Models\Publisher;
use App\Support\Isbn;
use Database\Seeders\BookSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function(): void {
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

it('seeds the whole hand-curated catalogue', function(): void {
    $this->seed(BookSeeder::class);

    // Temas de Hoy publishes two of them, hence seven publishers for eight books.
    expect(Book::count())->toBe(8)
        ->and(Publisher::count())->toBe(7);
});

it('carries a check-digit-valid ISBN for every seeded book', function(): void {
    $this->seed(BookSeeder::class);

    Book::pluck('isbn13')->each(function(string $isbn): void {
        expect(Isbn::isValid($isbn))->toBeTrue("{$isbn} is not a valid ISBN-13");
    });
});

it('gives every seeded book a real price, a publisher and a synopsis', function(): void {
    $this->seed(BookSeeder::class);

    Book::with('publisher')->get()->each(function(Book $book): void {
        expect($book->price_cents)->toBeGreaterThan(0, "{$book->title} has no price")
            ->and($book->publisher)->not->toBeNull("{$book->title} has no publisher")
            ->and($book->synopsis)->not->toBeEmpty("{$book->title} has no synopsis");
    });
});

it('records translators alongside authors', function(): void {
    $this->seed(BookSeeder::class);

    $schwarzenbach = Book::firstWhere('isbn13', '9788495587176');

    expect($schwarzenbach->title)->toBe('Muerte en Persia')
        ->and($schwarzenbach->authors_line)->toBe('Annemarie Schwarzenbach')
        ->and($schwarzenbach->contributorNames('traductor'))
        ->toBe(['Richard Gross', 'María Esperanza Romero'])
        ->and($schwarzenbach->original_language->value)->toBe('deu')
        ->and($schwarzenbach->original_title)->toBe('Tod in Persien');
});

it('joins co-authors into one authors line', function(): void {
    $this->seed(BookSeeder::class);

    expect(Book::firstWhere('isbn13', '9791387748586')->authors_line)
        ->toBe('Ana Garriga, Carmen Urbita');
});

it('downloads a cover for every book, including the ones no free API knows', function(): void {
    $this->seed(BookSeeder::class);

    Book::all()->each(function(Book $book): void {
        $cover = $book->cover();

        expect($cover)->not->toBeNull("{$book->title} has no cover")
            ->and($cover->file_name)->toBe("{$book->isbn13}.jpg");

        Storage::disk('public')->assertExists("{$cover->id}/{$cover->file_name}");
    });
});

it('can be run twice without duplicating anything', function(): void {
    $this->seed(BookSeeder::class);
    $this->seed(BookSeeder::class);

    expect(Book::count())->toBe(8)
        ->and(Publisher::count())->toBe(7);
});

it('seeds books with a slug when run through the default seeder', function(): void {
    $this->seed();

    expect(Book::count())->toBe(8);

    Book::all()->each(function(Book $book): void {
        expect($book->slug)->not->toBeEmpty("{$book->title} has no slug");
    });
});

/*
 | Covers downloaded by an earlier seed are still on the disk after the move to
 | the media library, so they are filed rather than fetched a second time.
 */
it('files a cover already on disk instead of downloading it again', function(): void {
    $onDisk = fakeCover(500, 700);
    Storage::disk('public')->put('covers/9788419812742.jpg', $onDisk);

    Http::fake([
        'openlibrary.org/api/books*' => Http::response([], 500),
        '*'                          => Http::response([], 500),
    ]);

    $this->seed(BookSeeder::class);

    $cover = Book::firstWhere('isbn13', '9788419812742')->cover();

    expect($cover)->not->toBeNull()
        ->and(Storage::disk('public')->get("{$cover->id}/{$cover->file_name}"))->toBe($onDisk)
        ->and(Storage::disk('public')->exists('covers/9788419812742.jpg'))->toBeTrue();
});

it('leaves a book without a cover when every source is unreachable', function(): void {
    Http::fake(fn() => throw new ConnectionException('Connection reset by peer'));

    $this->seed(BookSeeder::class);

    expect(Book::count())->toBe(8)
        ->and(Book::has('media')->count())->toBe(0);
});

it('does not attach a second copy of a cover when seeded twice', function(): void {
    $this->seed(BookSeeder::class);
    $this->seed(BookSeeder::class);

    Book::all()->each(function(Book $book): void {
        expect($book->getMedia(Book::COVERS_COLLECTION))->toHaveCount(
            1,
            "{$book->title} has more than one cover",
        );
    });
});

it('needs no metadata provider to cover the catalogue', function(): void {
    Http::fake([
        'openlibrary.org/*'    => Http::response([], 500),
        'www.googleapis.com/*' => Http::response([], 500),
        '*casadellibro.com/*'  => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $this->seed(BookSeeder::class);

    expect(Book::doesntHave('media')->count())->toBe(0);

    Http::assertNotSent(fn(Request $request): bool => str_contains($request->url(), 'openlibrary.org'));
});
