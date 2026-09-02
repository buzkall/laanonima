<?php

use App\Enums\BookAvailability;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Models\Author;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

it('shows the record a bookseller catalogued', function(): void {
    $book = Book::factory()->create([
        'title'        => 'Instrucción de novicias',
        'subtitle'     => 'Vidas del convento barroco para guiar tu presente',
        'contributors' => [
            ['name' => 'Ana Garriga', 'role' => 'author'],
            ['name' => 'Carmen Urbita', 'role' => 'author'],
        ],
        'synopsis'    => 'Detrás de los muros de un convento reina el silencio.',
        'pages'       => 288,
        'price_cents' => 2200,
        'isbn13'      => '9791387748586',
    ]);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('Instrucción de novicias')
        ->assertSee('Vidas del convento barroco para guiar tu presente')
        ->assertSee('Ana Garriga, Carmen Urbita')
        ->assertSee('Detrás de los muros de un convento reina el silencio.')
        ->assertSee('288')
        ->assertSee('9791387748586')
        ->assertSee('22,00');
});

it('paints the page in the colour read off the cover', function(): void {
    $book = Book::factory()->create(['cover_color' => '#3a7b86']);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('--cover: #3a7b86', escape: false);
});

it('falls back to the house red for a book with no cover', function(): void {
    $book = Book::factory()->create(['cover_color' => null]);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('--cover: ' . config('site.palette.fallback'), escape: false);
});

it('offers to keep a book aside while it is in stock', function(): void {
    $book = Book::factory()->create(['stock' => 3]);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee(__('books.public.in_stock.cta'))
        ->assertSee(rawurlencode(__('books.public.in_stock.subject', ['title' => $book->title])), escape: false)
        ->assertDontSee(__('books.public.out_of_stock.cta'));
});

it('offers to order a book that is not on the table', function(): void {
    $book = Book::factory()->outOfStock()->create();

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee(__('books.public.out_of_stock.cta'))
        ->assertSee(route('book-requests.create.book', $book))
        ->assertDontSee(__('books.public.in_stock.cta'));
});

it('pins the buy button to the bottom of a phone screen', function(): void {
    $book = Book::factory()->create(['stock' => 3]);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('data-buy-bar', escape: false)
        ->assertSee('data-buy', escape: false)
        /* The bar is the hero button pinned, so both point at the same place. */
        ->assertSeeInOrder([__('books.public.buy'), __('books.public.buy')]);
});

it('pins the order button for a book that is out of stock', function(): void {
    $book = Book::factory()->outOfStock()->create();

    $response = $this->get(route('books.show', $book))->assertOk();

    expect(substr_count($response->getContent(), __('books.public.out_of_stock.cta')))
        ->toBeGreaterThanOrEqual(3);
});

it('shows the other pictures of the book beside the object', function(): void {
    Storage::fake('public');

    $book = Book::factory()->create();
    $cover = $book->addMediaFromString(fakeCover())->usingFileName('cubierta.jpg')->toMediaCollection(Book::COVERS_COLLECTION);
    $spread = $book->addMediaFromString(fakeCover())->usingFileName('interior.jpg')->toMediaCollection(Book::COVERS_COLLECTION);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee($spread->getAvailableUrl(['thumb']), escape: false);
});

it('has nothing to show beside the object when the cover is the only picture', function(): void {
    Storage::fake('public');

    $book = Book::factory()->create();
    $book->addCoverFromString(fakeCover());

    expect($book->refresh()->gallery())->toBeEmpty();
});

it('says a word about each person on the title page, translator included', function(): void {
    Author::factory()->create(['name' => 'Gillian Anderson', 'bio' => '<p>Actriz conocida por <em>«Expediente X»</em>.</p>']);
    Author::factory()->create(['name' => 'Esther Cruz Santaella', 'bio' => '<p>Traductora del inglés al castellano.</p>']);
    $book = Book::factory()->create(['contributors' => [
        ['name' => 'Gillian Anderson', 'role' => 'author'],
        ['name' => 'Esther Cruz Santaella', 'role' => 'translator'],
    ]]);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('Actriz conocida por <em>«Expediente X»</em>.', escape: false)
        ->assertSee('<p>Traductora del inglés al castellano.</p>', escape: false);
});

it('points at other books by the same people', function(): void {
    $book = Book::factory()->create([
        'contributors' => [['name' => 'Almudena Grandes', 'role' => 'author']],
    ]);
    $sameAuthor = Book::factory()->create([
        'title'        => 'Las tres bodas de Manolita',
        'contributors' => [['name' => 'Almudena Grandes', 'role' => 'author']],
    ]);
    $someoneElse = Book::factory()->create([
        'title'        => 'La conjura de los necios',
        'contributors' => [['name' => 'John Kennedy Toole', 'role' => 'author']],
    ]);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('Las tres bodas de Manolita')
        ->assertDontSee('La conjura de los necios');
});

it('points at other books from the same imprint', function(): void {
    $blackie = Publisher::factory()->create(['name' => 'Blackie Books']);
    $book = Book::factory()->for($blackie)->create();
    $sibling = Book::factory()->for($blackie)->create(['title' => 'Cuaderno de faros']);
    $stranger = Book::factory()->create(['title' => 'Muerte en Persia']);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('Blackie Books')
        ->assertSee('Cuaderno de faros')
        ->assertDontSee('Muerte en Persia');
});

it('names the imprint on the record without giving it a band of its own', function(): void {
    $book = Book::factory()
        ->for(Publisher::factory()->create(['name' => 'Blackie Books', 'description' => null]))
        ->create();

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('Blackie Books')
        ->assertDontSee(__('books.public.publisher_kicker', ['publisher' => 'Blackie Books']));
});

it('never recommends a book that is hidden from the web', function(): void {
    $blackie = Publisher::factory()->create();
    $book = Book::factory()->for($blackie)->create();
    Book::factory()->for($blackie)->create([
        'title'     => 'Todavía sin publicar',
        'is_active' => false,
    ]);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertDontSee('Todavía sin publicar');
});

it('hides a book that is not visible on the web', function(): void {
    $book = Book::factory()->create(['is_active' => false]);

    $this->get(route('books.show', $book))->assertNotFound();
});

it('lets an administrator preview a book that is not visible on the web', function(): void {
    $book = Book::factory()->create(['is_active' => false]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('books.show', $book))
        ->assertOk()
        ->assertSee($book->title);
});

it('keeps a client out of a book that is not visible on the web', function(): void {
    $book = Book::factory()->create(['is_active' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('books.show', $book))
        ->assertNotFound();
});

it('links to the page from the edit screen', function(): void {
    $book = Book::factory()->create(['availability' => BookAvailability::Available]);

    $this->actingAs(User::factory()->admin()->create());

    livewire(EditBook::class, ['record' => $book->getRouteKey()])
        ->assertActionExists('viewOnSite')
        ->assertActionHasUrl('viewOnSite', route('books.show', $book));
});

it('links to the page from the book table', function(): void {
    $book = Book::factory()->create(['availability' => BookAvailability::Available]);

    $this->actingAs(User::factory()->admin()->create());

    livewire(ListBooks::class)
        ->assertActionExists(TestAction::make('viewOnSite')->table($book))
        ->assertActionHasUrl(TestAction::make('viewOnSite')->table($book), route('books.show', $book));
});
