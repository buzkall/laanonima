<?php

use App\Models\Author;
use App\Models\Book;
use Illuminate\Support\Facades\Storage;

it('lists everything on the shelf by one author', function(): void {
    $hers = Book::factory()->create([
        'title'        => 'Las tres bodas de Manolita',
        'contributors' => [['name' => 'Almudena Grandes', 'role' => 'author']],
    ]);
    Book::factory()->create([
        'title'        => 'La conjura de los necios',
        'contributors' => [['name' => 'John Kennedy Toole', 'role' => 'author']],
    ]);

    $this->get(route('authors.show', 'almudena-grandes'))
        ->assertOk()
        ->assertSee('Almudena Grandes')
        ->assertSee('Las tres bodas de Manolita')
        ->assertSee(route('books.show', $hers))
        ->assertDontSee('La conjura de los necios');
});

it('writes the name the way it is printed on the book, accents and all', function(): void {
    Book::factory()->create(['contributors' => [['name' => 'Ramón J. Sénder', 'role' => 'author']]]);

    $this->get(route('authors.show', 'ramon-j-sender'))
        ->assertOk()
        ->assertSee('Ramón J. Sénder');
});

it('finds a co-writer from either name', function(): void {
    Book::factory()->create([
        'title'        => 'Instrucción de novicias',
        'contributors' => [
            ['name' => 'Ana Garriga', 'role' => 'author'],
            ['name' => 'Carmen Urbita', 'role' => 'author'],
        ],
    ]);

    foreach (['ana-garriga', 'carmen-urbita'] as $slug) {
        $this->get(route('authors.show', $slug))
            ->assertOk()
            ->assertSee('Instrucción de novicias');
    }
});

it('gives a translator a shelf of their own too', function(): void {
    Book::factory()->create([
        'title'        => 'Quiero',
        'contributors' => [
            ['name' => 'Gillian Anderson', 'role' => 'author'],
            ['name' => 'Esther Cruz Santaella', 'role' => 'translator'],
        ],
    ]);

    $this->get(route('authors.show', 'esther-cruz-santaella'))
        ->assertOk()
        ->assertSee('Esther Cruz Santaella')
        ->assertSee('Quiero');
});

it('never shows a book that is hidden from the web', function(): void {
    Book::factory()->create([
        'title'        => 'Todavía sin publicar',
        'is_active'    => false,
        'contributors' => [['name' => 'Almudena Grandes', 'role' => 'author']],
    ]);

    $this->get(route('authors.show', 'almudena-grandes'))->assertNotFound();
});

it('is a 404 for an author we have nothing by', function(): void {
    $this->get(route('authors.show', 'nadie-en-absoluto'))->assertNotFound();
});

it('links to everyone on the title page from the book page', function(): void {
    $book = Book::factory()->create([
        'contributors' => [
            ['name' => 'Ana Garriga', 'role' => 'author'],
            ['name' => 'Esther Cruz Santaella', 'role' => 'translator'],
        ],
    ]);

    $this->get(route('books.show', $book))
        ->assertOk()
        ->assertSee(route('authors.show', 'ana-garriga'))
        ->assertSee(route('authors.show', 'esther-cruz-santaella'));
});

it('follows the contributors when a book is refiled under someone else', function(): void {
    $book = Book::factory()->create(['contributors' => [['name' => 'Ana Garriga', 'role' => 'author']]]);

    expect($book->authors_line)->toBe('Ana Garriga');

    $book->syncContributors([['name' => 'Carmen Urbita', 'role' => 'author']]);

    expect($book->refresh()->authors_line)->toBe('Carmen Urbita');

    $this->get(route('authors.show', 'ana-garriga'))->assertNotFound();
    $this->get(route('authors.show', 'carmen-urbita'))->assertOk();
});

it('files the same person on two books as one record', function(): void {
    Book::factory()->count(2)->create(['contributors' => [['name' => 'Almudena Grandes', 'role' => 'author']]]);

    expect(Author::where('slug', 'almudena-grandes')->count())->toBe(1)
        ->and(Author::firstWhere('slug', 'almudena-grandes')->books)->toHaveCount(2);
});

it('tells the reader who the author is, in the shop\'s own words', function(): void {
    Author::factory()->create([
        'name' => 'Almudena Grandes',
        'bio'  => '<p>Escritora madrileña, <strong>cronista de la posguerra</strong>.</p><p>Murió en 2021.</p>',
    ]);
    Book::factory()->create(['contributors' => [['name' => 'Almudena Grandes', 'role' => 'author']]]);

    $this->get(route('authors.show', 'almudena-grandes'))
        ->assertOk()
        ->assertSee('<strong>cronista de la posguerra</strong>', escape: false)
        ->assertSee('<p>Murió en 2021.</p>', escape: false)
        ->assertSee('content="Escritora madrileña, cronista de la posguerra. Murió en 2021."', escape: false)
        ->assertSee(__('books.public.author.intro', ['name' => 'Almudena Grandes']));
});

it('rewrites the authors line of every book when a person is renamed', function(): void {
    $book = Book::factory()->create(['contributors' => [['name' => 'Almudena Grande', 'role' => 'author']]]);

    Author::firstWhere('slug', 'almudena-grande')->update(['name' => 'Almudena Grandes']);

    expect($book->refresh()->authors_line)->toBe('Almudena Grandes');
});

it('shows the portrait beside the name, and credits the photographer', function(): void {
    Storage::fake('public');

    $author = Author::factory()->create(['name' => 'Leila Guerriero']);
    Book::factory()->create(['contributors' => [['name' => 'Leila Guerriero', 'role' => 'author']]]);

    $author->addMediaFromString(fakeCover(480, 640))
        ->usingFileName('guerriero-leila.jpg')
        ->withCustomProperties(['credit' => ['artist' => 'Casa de América', 'license' => 'CC BY 2.0']])
        ->toMediaCollection(Author::PORTRAIT_COLLECTION);

    $this->get(route('authors.show', 'leila-guerriero'))
        ->assertOk()
        ->assertSee('alt="Leila Guerriero"', escape: false)
        ->assertSee($author->fresh()->portraitUrl('thumb'))
        ->assertSee(__('books.public.author.portrait_credit', ['artist' => 'Casa de América', 'license' => 'CC BY 2.0']));
});

it('says nothing about a photo the shop uploaded itself', function(): void {
    Storage::fake('public');

    $author = Author::factory()->create(['name' => 'Leila Guerriero']);
    Book::factory()->create(['contributors' => [['name' => 'Leila Guerriero', 'role' => 'author']]]);

    $author->addMediaFromString(fakeCover(480, 640))
        ->usingFileName('leila.jpg')
        ->toMediaCollection(Author::PORTRAIT_COLLECTION);

    $this->get(route('authors.show', 'leila-guerriero'))
        ->assertOk()
        ->assertSee('alt="Leila Guerriero"', escape: false)
        ->assertDontSee(__('books.public.author.portrait_credit', ['artist' => '', 'license' => '']));
});

/* The control is a button and not a link, so the only thing a request can
   check is what it was handed: the page's own address and the sentence that
   travels with it. What the browser does with them is `resources/js/share.js`. */
it('offers the author\'s shelf to be shared, with a line that names the shop', function(): void {
    Book::factory()->create(['contributors' => [['name' => 'Almudena Grandes', 'role' => 'author']]]);

    $this->get(route('authors.show', 'almudena-grandes'))
        ->assertOk()
        ->assertSee(__('books.public.share.action'))
        ->assertSeeHtml('data-share-url="' . e(route('authors.show', 'almudena-grandes')) . '"')
        ->assertSee(__('books.public.share.author_message', [
            'name' => 'Almudena Grandes',
            'shop' => config('app.name'),
        ]));
});
