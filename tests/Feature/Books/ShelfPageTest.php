<?php

use App\Enums\BookBinding;
use App\Models\Book;
use App\Support\ShelfArrangement;
use App\Support\ShelfBook;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

beforeEach(function(): void {
    Storage::fake('public');
});

/**
 * Books the shelf will actually stand up: it only shows the ones there is a
 * picture of, so a factory book with no media never reaches it.
 *
 * @param  array<string, mixed>  $attributes
 * @return Collection<int, Book>
 */
function shelved(int $count = 1, array $attributes = []): Collection
{
    return Book::factory()->count($count)->create($attributes)
        ->each(fn(Book $book): mixed => $book->addCoverFromString(fakeCover()));
}

it('stands every book that is on the web on the shelf', function(): void {
    $onTheWeb = shelved(attributes: ['title' => 'Cuaderno de faros'])->first();
    $withdrawn = shelved(attributes: ['title' => 'Nunca lo subimos', 'is_active' => false])->first();
    $noCover = Book::factory()->create(['title' => 'Sin foto ninguna']);

    $this->get(route('books.shelf'))
        ->assertOk()
        ->assertSee($onTheWeb->title)
        ->assertSee(route('books.show', $onTheWeb))
        ->assertDontSee($withdrawn->title)
        ->assertDontSee($noCover->title);
});

it('draws a measured book at the size it was measured', function(): void {
    shelved(attributes: [
        'width_mm'     => 132,
        'height_mm'    => 204,
        'thickness_mm' => 17,
    ]);

    $this->get(route('books.shelf'))
        ->assertOk()
        ->assertSee('--mm-w: 132; --mm-h: 204; --mm-d: 17', escape: false);
});

it('stands a book with no measurements at the ordinary size for its binding', function(): void {
    /* Nothing measured, so the shelf falls back to config: a hardback is
       150 x 230mm, and 300 pages of paper plus boards is 21mm of spine. */
    shelved(attributes: [
        'binding'      => BookBinding::Hardback,
        'pages'        => 300,
        'width_mm'     => null,
        'height_mm'    => null,
        'thickness_mm' => null,
    ]);

    $this->get(route('books.shelf'))
        ->assertOk()
        ->assertSee('--mm-w: 150; --mm-h: 230; --mm-d: 21', escape: false);
});

it('gives a book with neither measurements nor pages a spine anyway', function(): void {
    shelved(attributes: [
        'binding'      => null,
        'pages'        => null,
        'width_mm'     => null,
        'height_mm'    => null,
        'thickness_mm' => null,
    ]);

    $this->get(route('books.shelf'))
        ->assertOk()
        ->assertSee('--mm-w: 140; --mm-h: 210; --mm-d: 20', escape: false);
});

it('marks which books are standing at a guessed size', function(): void {
    /* The script takes an unmeasured book's proportions off its cover rather
       than off the binding guess, so it has to be told which is which. */
    shelved(attributes: ['width_mm' => 132, 'height_mm' => 204]);
    shelved(attributes: ['width_mm' => null, 'height_mm' => null]);

    $page = $this->get(route('books.shelf'))->assertOk()->getContent();

    expect($page)->toContain('data-measured="1"')
        ->and($page)->toContain('data-measured="0"');
});

it('never turns two neighbouring books cover-first', function(): void {
    shelved(9);

    /* Two covers side by side read as a mistake at the table rather than as a
       choice, and a plain random draw puts them there about one visit in five. */
    foreach (range(1, 40) as $ignored) {
        preg_match_all('/data-face="(\d)"/', $this->get(route('books.shelf'))->getContent(), $found);

        expect(implode('', $found[1]))->not->toContain('11');
    }
});

it('puts the shelf back exactly as it was when given its seed', function(): void {
    shelved(8);

    $page = $this->get(route('books.shelf'))->assertOk()->getContent();

    preg_match('/data-seed="(\d+)"/', $page, $seed);
    preg_match_all('/data-title="([^"]+)"/', $page, $titles);
    preg_match_all('/data-face="(\d)"/', $page, $faces);

    $again = ShelfArrangement::of(Book::query()->onStage()->get(), (int)$seed[1]);

    expect($again->books->map(fn(ShelfBook $shelved): string => $shelved->book->title)->all())
        ->toBe($titles[1])
        ->and($again->books->map(fn(ShelfBook $shelved): string => $shelved->facesOut ? '1' : '0')->all())
        ->toBe($faces[1]);
});

it('arranges the same books differently under a different seed', function(): void {
    Book::factory()->count(8)->create();
    $books = Book::query()->onShelf()->get();

    $order = fn(int $seed): string => ShelfArrangement::of($books, $seed)
        ->books->map(fn(ShelfBook $shelved): int => $shelved->book->id)->implode('-');

    expect(collect(range(1, 5))->map($order)->unique()->count())->toBeGreaterThan(1);
});

it('offers each cover at twice the density for a dense screen', function(): void {
    shelved();

    $this->get(route('books.shelf'))
        ->assertOk()
        ->assertSee('-retina.jpg 2x', escape: false);
});

it('turns exactly two of the books cover-first and the rest spine-first', function(): void {
    /* Piles off: a book lying flat has no cover to turn outwards, so they would
       only muddy the count this test is about. */
    config()->set('site.shelf.stacks', 0);
    shelved(6);

    $page = $this->get(route('books.shelf'))->assertOk()->getContent();

    expect(substr_count($page, 'data-face="1"'))->toBe(config('site.shelf.facing_out'))
        ->and(substr_count($page, 'data-face="0"'))->toBe(4);
});

it('turns what it has round when the shelf is shorter than that', function(): void {
    shelved();

    $page = $this->get(route('books.shelf'))->assertOk()->getContent();

    expect(substr_count($page, 'data-face="1"'))->toBe(1);
});

it('lays a few books flat in a pile', function(): void {
    Book::factory()->count(10)->create();

    $arrangement = ShelfArrangement::of(Book::query()->onShelf()->get(), seed: 1);
    $pile = $arrangement->books->filter(fn(ShelfBook $shelved): bool => $shelved->liesFlat());

    expect($pile->count())->toBeGreaterThanOrEqual(2)
        ->and($pile->count())->toBeLessThanOrEqual(3)
        ->and($pile->pluck('stack')->unique()->count())->toBe(1);
});

it('puts the biggest book at the bottom of the pile', function(): void {
    Book::factory()->count(10)->create();

    $pile = ShelfArrangement::of(Book::query()->onShelf()->get(), seed: 1)
        ->books
        ->filter(fn(ShelfBook $shelved): bool => $shelved->liesFlat())
        ->map(fn(ShelfBook $shelved): int => $shelved->footprintArea())
        ->values();

    /* Row order is bottom of the pile first, and nothing sits on something
       smaller than itself. */
    expect($pile->all())->toBe($pile->sortDesc()->values()->all());
});

it('keeps the pile together in one place on the board', function(): void {
    Book::factory()->count(10)->create();

    $flat = ShelfArrangement::of(Book::query()->onShelf()->get(), seed: 1)
        ->books
        ->map(fn(ShelfBook $shelved): string => $shelved->liesFlat() ? 'f' : '.')
        ->implode('');

    expect($flat)->toMatch('/^\.*f{2,3}\.*$/');
});

it('never lays books flat on a shelf too short to spare them', function(): void {
    Book::factory()->count(5)->create();

    $arrangement = ShelfArrangement::of(Book::query()->onShelf()->get(), seed: 1);

    expect($arrangement->books->filter(fn(ShelfBook $shelved): bool => $shelved->liesFlat()))->toBeEmpty();
});

it('never turns a book in a pile cover-first', function(): void {
    Book::factory()->count(10)->create();

    foreach (range(1, 30) as $seed) {
        $arrangement = ShelfArrangement::of(Book::query()->onShelf()->get(), $seed);

        expect($arrangement->books->filter(
            fn(ShelfBook $shelved): bool => $shelved->liesFlat() && $shelved->facesOut,
        ))->toBeEmpty();
    }
});

it('writes the author down the spine by surname', function(): void {
    shelved(attributes: [
        'title'        => 'Panza de burro',
        'contributors' => [['name' => 'Andrea Abreu', 'role' => 'author']],
    ]);

    $this->get(route('books.shelf'))
        ->assertOk()
        ->assertSee('Panza de burro · Abreu');
});

it('links to the shelf from every page', function(): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('books.shelf'))
        ->assertSee(__('books.public.shelf.title'));
});

it('says so rather than showing an empty shelf', function(): void {
    $this->get(route('books.shelf'))
        ->assertOk()
        ->assertSee(__('books.public.home.empty'));
});
