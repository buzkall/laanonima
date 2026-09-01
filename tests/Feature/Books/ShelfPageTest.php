<?php

use App\Enums\BookBinding;
use App\Models\Book;

it('stands every book that is on the web on the shelf', function(): void {
    $onTheWeb = Book::factory()->create(['title' => 'Cuaderno de faros']);
    $withdrawn = Book::factory()->create(['title' => 'Nunca lo subimos', 'is_active' => false]);

    $this->get(route('books.shelf'))
        ->assertOk()
        ->assertSee($onTheWeb->title)
        ->assertSee(route('books.show', $onTheWeb))
        ->assertDontSee($withdrawn->title);
});

it('draws a measured book at the size it was measured', function(): void {
    Book::factory()->create([
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
    Book::factory()->create([
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
    Book::factory()->create([
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
    Book::factory()->create(['width_mm' => 132, 'height_mm' => 204]);
    Book::factory()->create(['width_mm' => null, 'height_mm' => null]);

    $page = $this->get(route('books.shelf'))->assertOk()->getContent();

    expect($page)->toContain('data-measured="1"')
        ->and($page)->toContain('data-measured="0"');
});

it('turns exactly two of the books cover-first and the rest spine-first', function(): void {
    Book::factory()->count(6)->create();

    $page = $this->get(route('books.shelf'))->assertOk()->getContent();

    expect(substr_count($page, 'data-face="1"'))->toBe(config('site.shelf.facing_out'))
        ->and(substr_count($page, 'data-face="0"'))->toBe(4);
});

it('turns what it has round when the shelf is shorter than that', function(): void {
    Book::factory()->create();

    $page = $this->get(route('books.shelf'))->assertOk()->getContent();

    expect(substr_count($page, 'data-face="1"'))->toBe(1);
});

it('shuffles the row on every visit', function(): void {
    Book::factory()->count(6)->create();

    $orders = collect(range(1, 5))->map(function(): string {
        preg_match_all('/data-title="([^"]+)"/', $this->get(route('books.shelf'))->getContent(), $found);

        return implode('|', $found[1]);
    });

    /* Six books over five visits: identical every time is a 720^-4 accident,
       so this failing means the shelf is sorted rather than shuffled. */
    expect($orders->unique()->count())->toBeGreaterThan(1);
});

it('writes the author down the spine by surname', function(): void {
    Book::factory()->create([
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
