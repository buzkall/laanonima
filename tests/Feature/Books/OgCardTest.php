<?php

use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use App\Support\Og\OgCardKey;
use Illuminate\Support\Facades\Storage;

beforeEach(function(): void {
    Storage::fake('public');
    Storage::fake('og');
});

/**
 * The card as it comes off the disk, so the assertions read the bytes that were
 * actually filed rather than a streamed response.
 *
 * @return array{0: int, 1: int, 2: int}
 */
function storedCard(OgCardKey $key): array
{
    Storage::disk('og')->assertExists($key->path());

    return getimagesizefromstring((string)Storage::disk('og')->get($key->path()));
}

it('draws a book a card at the size every scraper crops to', function(): void {
    $book = Book::factory()->create(['title' => 'Instrucción de novicias']);
    $book->addCoverFromString(fakeCover());

    $this->get(route('books.show', $book))->assertOk();

    [$width, $height, $type] = storedCard(OgCardKey::forBook($book->fresh(['media'])));

    expect($width)->toBe(1200)
        ->and($height)->toBe(630)
        ->and($type)->toBe(IMAGETYPE_JPEG);
});

/*
 | A book with no picture is the common case, not an edge one: the card falls
 | back to the brand's mark on the book's own color rather than to nothing.
 */
it('still draws a card for a book with no cover', function(): void {
    $book = Book::factory()->create();

    $this->get(route('books.show', $book))->assertOk();

    [$width, $height] = storedCard(OgCardKey::forBook($book));

    expect($width)->toBe(1200)->and($height)->toBe(630);
});

it('points the book page at its own card, absolutely', function(): void {
    $book = Book::factory()->create();
    $card = OgCardKey::forBook($book)->url();

    expect($card)->toStartWith('http');

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('<meta property="og:image" content="' . $card . '" />', escape: false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image" />', escape: false)
        ->assertSee('<meta property="og:image:width" content="1200" />', escape: false)
        ->assertSee('<meta property="og:image:height" content="630" />', escape: false);
});

it('draws the card once and serves the same file after that', function(): void {
    $book = Book::factory()->create();

    $this->get(route('books.show', $book))->assertOk();
    $drawn = Storage::disk('og')->lastModified(OgCardKey::forBook($book)->path());

    $this->get(route('books.show', $book))->assertOk();

    expect(Storage::disk('og')->allFiles())->toHaveCount(1)
        ->and(Storage::disk('og')->lastModified(OgCardKey::forBook($book)->path()))->toBe($drawn);
});

/*
 | The fingerprint is read off the record rather than stored beside it, so a
 | retitled book draws a new card without anything having to listen for the
 | change -- and the one it used to have is swept, or the directory would grow
 | by a card per edit forever.
 */
it('redraws the card when the book changes, and drops the old one', function(): void {
    $book = Book::factory()->create(['title' => 'Primer título']);

    $this->get(route('books.show', $book))->assertOk();
    $before = OgCardKey::forBook($book)->path();

    $book->update(['title' => 'Otro título distinto']);

    $this->get(route('books.show', $book->fresh()))->assertOk();
    $after = OgCardKey::forBook($book->fresh())->path();

    expect($after)->not->toBe($before);
    Storage::disk('og')->assertExists($after);
    Storage::disk('og')->assertMissing($before);
});

/*
 | A bookseller can preview a book before it is on the web. Drawing its card
 | would write the title and the cover to a public path while the record is
 | still a draft, so the preview shares without a picture instead.
 */
it('draws no card for a book that is not on the web yet', function(): void {
    $book = Book::factory()->create(['is_active' => false]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('books.show', $book))
        ->assertOk()
        ->assertDontSee('og:image')
        ->assertSee('<meta name="twitter:card" content="summary" />', escape: false);

    expect(Storage::disk('og')->allFiles())->toBeEmpty();
});

it('gives an author page a card of their shelf', function(): void {
    $book = Book::factory()->create([
        'contributors' => [['name' => 'Almudena Grandes', 'role' => 'author']],
    ]);
    $book->addCoverFromString(fakeCover());

    $author = $book->fresh(['contributors.author'])->contributors->first()->author;

    $this->get(route('authors.show', $author))
        ->assertOk()
        ->assertSee('og:image', escape: false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image" />', escape: false);

    expect(Storage::disk('og')->allFiles())->toHaveCount(1);
});

it('gives a publisher page a card of its shelf', function(): void {
    $publisher = Publisher::factory()->create();
    Book::factory()->for($publisher)->create();

    $this->get(route('publishers.show', $publisher))
        ->assertOk()
        ->assertSee('og:image', escape: false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image" />', escape: false);

    expect(Storage::disk('og')->allFiles())->toHaveCount(1);
});
