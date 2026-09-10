<?php

use App\Models\Author;
use App\Models\Book;
use App\Support\Cupida\CupidaCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The commands write author-photos.json, so they get a scratch copy of the
 * fixture catalog rather than the committed one.
 */
beforeEach(function(): void {
    $this->pool = base_path('tests/Fixtures/cupida/scratch-' . getmypid());

    mkdir($this->pool, 0755, recursive: true);

    foreach (['authors', 'books', 'themes', 'author-photos'] as $file) {
        copy(base_path("tests/Fixtures/cupida/catalog/{$file}.json"), "{$this->pool}/{$file}.json");
    }

    config()->set('cupida.data_path', $this->pool);

    /* The floor the command walks is the deck's, and it is written for the real
       pool. Ten of the eleven fixture authors have one book, so left alone this
       command would be handed Leila Guerriero and nobody else -- the same
       reason `useCupidaFixture()` lowers it, which this file cannot call
       because it needs a writable copy of the catalog. */
    config()->set('cupida.deck.author_min_books', 1);

    app()->forgetInstance(CupidaCatalog::class);

    Storage::fake('portraits');
});

afterEach(function(): void {
    array_map(unlink(...), glob("{$this->pool}/*") ?: []);
    rmdir($this->pool);
});

/** The fixture authors with a downloadable photo. */
function matchedFixtureSlugs(): array
{
    return ['bermejo-ana', 'guerriero-leila', 'mayayo-patricia', 'parker-sarah-a', 'rodriguez-elaine-vilar'];
}

function portraitsOnDisk(string $pool): array
{
    return json_decode((string)file_get_contents("{$pool}/author-photos.json"), true);
}

it('never asks again about an author it already has a verdict on', function(): void {
    Http::fake();

    $this->artisan('cupida:portraits:resolve')->assertSuccessful();

    /* Recording the misses is the point: without a no_image row, the names
       Wikidata will never answer cost two requests apiece on every run,
       forever. The authors with no row yet are looked up; these five are not. */
    foreach (['Leila+Guerriero', 'Ana+Bermejo', 'Julia+Vera', 'Kaoru+Ito', 'Sara+Torres'] as $name) {
        Http::assertNotSent(fn($request): bool => str_contains($request->url(), $name));
    }
});

it('asks again about the misses when told to', function(): void {
    Http::fake([
        'www.wikidata.org/w/api.php?action=wbsearchentities*' => Http::response(apiFixture('portraits/search-sastre')),
        'www.wikidata.org/w/api.php*'                         => Http::response(apiFixture('portraits/entities-sastre')),
        'commons.wikimedia.org/*'                             => Http::response(apiFixture('portraits/imageinfo-sastre')),
        '*'                                                   => Http::response(fakeCover(1200, 1600)),
    ]);

    $this->artisan('cupida:portraits:resolve --retry-misses')->assertSuccessful();

    /* vera-julia was no_image and is asked about again; ito-kaoru is pinned
       no_person and is not. */
    Http::assertSent(fn($request): bool => str_contains($request->url(), 'Julia+Vera')
        || str_contains($request->url(), 'Julia%20Vera'));

    Http::assertNotSent(fn($request): bool => str_contains($request->url(), 'Kaoru'));
});

it('keeps a hand-pinned entry when the pool is looked up from scratch', function(): void {
    Http::fake(['*' => Http::response(['search' => []])]);

    $before = portraitsOnDisk($this->pool)['ito-kaoru'];

    $this->artisan('cupida:portraits:resolve --fresh')->assertSuccessful();

    /* --fresh here deliberately does not mean what it means in cupida:scrape.
       An afternoon of reviewing faces one at a time is not something a flag
       gets to destroy silently. */
    expect(portraitsOnDisk($this->pool)['ito-kaoru'])->toBe($before);
});

it('throws the pins away too when explicitly told to', function(): void {
    Http::fake(['*' => Http::response(['search' => []])]);

    $this->artisan('cupida:portraits:resolve --fresh --forget-pins')->assertSuccessful();

    /* The hand-made verdict is gone and the author was looked up again like
       anybody else -- which is exactly what the flag says it does, and why it
       is not what --fresh does on its own. */
    $entry = portraitsOnDisk($this->pool)['ito-kaoru'];

    expect($entry['status'])->toBe('no_match')
        ->and($entry['pinned'])->toBeFalse();
});

it('refuses to pin without naming exactly one author', function(): void {
    Http::fake();

    /* A --qid applied across a --limit of forty has no undo. */
    $this->artisan('cupida:portraits:resolve --qid=Q123')->assertFailed();
    $this->artisan('cupida:portraits:resolve --qid=Q123 --only=a,b')->assertFailed();

    Http::assertNothingSent();
});

it('marks an author as not a person without asking anybody', function(): void {
    Http::fake();

    $this->artisan('cupida:portraits:resolve --only=kingfisher-t --none')->assertSuccessful();

    $entry = portraitsOnDisk($this->pool)['kingfisher-t'];

    expect($entry['status'])->toBe('no_person')
        ->and($entry['pinned'])->toBeTrue();

    Http::assertNothingSent();
});

it('recognizes a collective by its name before making a request', function(): void {
    Http::fake();

    /* The shop files anthologies under an author name and respells it every
       time the catalog grows, so the recurring shape is matched rather than
       listed by hand. */
    file_put_contents("{$this->pool}/authors.json", json_encode([
        ['name' => 'Vv. Aa.', 'shop_name' => 'Vv. Aa.', 'slug' => 'vv-aa', 'titles' => [], 'books' => 9],
        ['name' => 'Varios Autores', 'shop_name' => 'Varios Autores', 'slug' => 'varios-autores', 'titles' => [], 'books' => 8],
    ]));
    app()->forgetInstance(CupidaCatalog::class);

    $this->artisan('cupida:portraits:resolve')->assertSuccessful();

    $written = portraitsOnDisk($this->pool);

    expect($written['vv-aa']['status'])->toBe('no_person')
        ->and($written['varios-autores']['status'])->toBe('no_person');

    Http::assertNothingSent();
});

it('records the credit and the color it read off the bytes', function(): void {
    file_put_contents("{$this->pool}/author-photos.json", '{}');
    app()->forgetInstance(CupidaCatalog::class);

    Http::fake([
        'www.wikidata.org/w/api.php?action=wbsearchentities*' => Http::response(apiFixture('portraits/search-sastre')),
        'www.wikidata.org/w/api.php*'                         => Http::response(apiFixture('portraits/entities-sastre')),
        'commons.wikimedia.org/*'                             => Http::response(apiFixture('portraits/imageinfo-sastre')),
        '*'                                                   => Http::response(fakeCover(1200, 1600)),
    ]);

    $this->artisan('cupida:portraits:resolve --only=guerriero-leila')->assertSuccessful();

    $entry = portraitsOnDisk($this->pool)['guerriero-leila'];

    expect($entry['status'])->toBe('matched')
        ->and($entry['photo'])->toBe('guerriero-leila.jpg')
        ->and($entry['credit']['artist'])->toBe('Florenciac')
        ->and($entry['credit']['license'])->toBe('CC BY-SA 4.0')
        ->and($entry['image_url'])->not->toContain('utm_')
        ->and($entry['color'])->toMatch('/^#[0-9a-f]{6}$/');

    Storage::disk('portraits')->assertExists('guerriero-leila.jpg');
});

it('does not pin a lookup that never landed', function(): void {
    Http::fake(['*' => Http::response(['search' => []])]);

    /* A pin is what a person decided, and naming a qid is only half of that:
       the item still has to come back. Recorded as pinned, a Wikidata hiccup
       during this run would be frozen as a verdict -- outstanding() skips a
       pinned row forever, --retry-misses does not reach one, and it reads
       exactly like a miss somebody confirmed by hand. */
    $this->artisan('cupida:portraits:resolve --only=ruiz-marta --qid=Q123')->assertSuccessful();

    $entry = portraitsOnDisk($this->pool)['ruiz-marta'];

    expect($entry['status'])->toBe('no_match')
        ->and($entry['pinned'])->toBeFalse();

    /* Which is to say the next run can still ask. */
    Http::fake(['*' => Http::response(['search' => []])]);

    $this->artisan('cupida:portraits:resolve --retry-misses')->assertSuccessful();

    Http::assertSent(fn($request): bool => str_contains($request->url(), 'Marta+Ruiz')
        || str_contains($request->url(), 'Marta%20Ruiz'));
});

it('takes the face away with the row when a match is downgraded', function(): void {
    Storage::disk('portraits')->put('guerriero-leila.jpg', fakeCover(480, 640));

    Http::fake(['*' => Http::response(['search' => []])]);

    $this->artisan('cupida:portraits:resolve --only=guerriero-leila')->assertSuccessful();

    $entry = portraitsOnDisk($this->pool)['guerriero-leila'];

    expect($entry['status'])->toBe('no_match')
        ->and($entry['photo'])->toBeNull();

    /* CupidaCatalog::portrait() checks the disk as well as the row, so a file
       left behind keeps showing on a card the metadata says has no face. */
    Storage::disk('portraits')->assertMissing('guerriero-leila.jpg');

    app()->forgetInstance(CupidaCatalog::class);

    expect(app(CupidaCatalog::class)->portrait('guerriero-leila'))->toBeNull();
});

it('drops an unusable photo but keeps the author in the deck', function(): void {
    Storage::disk('portraits')->put('guerriero-leila.jpg', fakeCover(480, 640));
    Http::fake();

    /* The case the occupation guard structurally cannot see: the right writer,
       whose only picture on Commons is a statue, a book cover, a group shot --
       or, in the run that prompted this flag, their own signature logo.
       `--none` would be a lie, and would take their card away as well. */
    $this->artisan('cupida:portraits:resolve --only=guerriero-leila --reject')->assertSuccessful();

    $entry = portraitsOnDisk($this->pool)['guerriero-leila'];

    expect($entry['status'])->toBe('rejected')
        ->and($entry['pinned'])->toBeTrue()
        ->and($entry['photo'])->toBeNull()
        /* Remembered so nothing ever picks that item again. */
        ->and($entry['rejected_qid'])->toBe('Q000000001');

    /* The stale face is gone from the disk, and she still gets dealt. */
    Storage::disk('portraits')->assertMissing('guerriero-leila.jpg');

    app()->forgetInstance(CupidaCatalog::class);

    expect(app(CupidaCatalog::class)->isCollective('guerriero-leila'))->toBeFalse()
        ->and(app(CupidaCatalog::class)->portrait('guerriero-leila'))->toBeNull();

    Http::assertNothingSent();
});

it('fetch skips a portrait already on disk', function(): void {
    foreach (matchedFixtureSlugs() as $slug) {
        Storage::disk('portraits')->put("{$slug}.jpg", fakeCover(480, 640));
    }

    Http::fake();

    $this->artisan('cupida:portraits:fetch')->assertSuccessful();

    Http::assertNothingSent();
});

it('fetch downloads what the deploy is missing', function(): void {
    Http::fake(['*' => Http::response(fakeCover(1200, 1600), 200, ['Content-Type' => 'image/jpeg'])]);

    $this->artisan('cupida:portraits:fetch')->assertSuccessful();

    foreach (matchedFixtureSlugs() as $slug) {
        Storage::disk('portraits')->assertExists("{$slug}.jpg");
    }

    /* no_image and no_person rows have nothing to download. */
    Storage::disk('portraits')->assertMissing('vera-julia.jpg');
    Storage::disk('portraits')->assertMissing('ito-kaoru.jpg');
});

it('fetch files the portrait on the author page when the shop has the writer', function(): void {
    Storage::fake('public');
    Book::factory()->create(['contributors' => [['name' => 'Leila Guerriero', 'role' => 'author']]]);

    Http::fake(['*' => Http::response(fakeCover(1200, 1600), 200, ['Content-Type' => 'image/jpeg'])]);

    $this->artisan('cupida:portraits:fetch')->assertSuccessful();

    $portrait = Author::firstWhere('slug', 'leila-guerriero')->portrait();

    expect($portrait)->not->toBeNull()
        ->and($portrait->file_name)->toBe('guerriero-leila.jpg')
        ->and($portrait->getCustomProperty('credit.license'))->toBe('CC BY-SA 4.0')
        ->and($portrait->getCustomProperty('credit.artist'))->toBe('Florenciac');

    /* It links, it never creates: the other four faces have nobody to hang on. */
    expect(Author::count())->toBe(1);
});

it('fetch files a portrait already on disk on an author who has none yet', function(): void {
    Storage::fake('public');
    Book::factory()->create(['contributors' => [['name' => 'Leila Guerriero', 'role' => 'author']]]);

    foreach (matchedFixtureSlugs() as $slug) {
        Storage::disk('portraits')->put("{$slug}.jpg", fakeCover(480, 640));
    }

    Http::fake();

    $this->artisan('cupida:portraits:fetch')->assertSuccessful();

    Http::assertNothingSent();

    expect(Author::firstWhere('slug', 'leila-guerriero')->portrait())->not->toBeNull();
});

it('fetch leaves a portrait the bookseller chose alone unless it downloads again', function(): void {
    Storage::fake('public');
    Book::factory()->create(['contributors' => [['name' => 'Leila Guerriero', 'role' => 'author']]]);

    $author = Author::firstWhere('slug', 'leila-guerriero');
    $author->addMediaFromString(fakeCover(480, 640))
        ->usingFileName('chosen.jpg')
        ->toMediaCollection(Author::PORTRAIT_COLLECTION);

    foreach (matchedFixtureSlugs() as $slug) {
        Storage::disk('portraits')->put("{$slug}.jpg", fakeCover(480, 640));
    }

    Http::fake(['*' => Http::response(fakeCover(1200, 1600), 200, ['Content-Type' => 'image/jpeg'])]);

    $this->artisan('cupida:portraits:fetch')->assertSuccessful();

    expect($author->fresh()->portrait()->file_name)->toBe('chosen.jpg');

    $this->artisan('cupida:portraits:fetch --force')->assertSuccessful();

    expect($author->fresh()->portrait()->file_name)->toBe('guerriero-leila.jpg');
});

it('fetch exits zero when a download fails, so a deploy is never blocked', function(): void {
    Http::fake(['*' => Http::response('', 500)]);

    /* A portrait that did not arrive is already a faceless card, not a broken
       image: the catalog checks the disk and not just the metadata. */
    $this->artisan('cupida:portraits:fetch')->assertSuccessful();

    Storage::disk('portraits')->assertMissing('guerriero-leila.jpg');
});

it('writes a contact sheet without making a single request', function(): void {
    Http::fake();

    $this->artisan('cupida:portraits:resolve --sheet')->assertSuccessful();

    $sheet = (string)file_get_contents(storage_path('app/private/cupida-portraits.html'));

    expect($sheet)->toContain('Leila Guerriero')
        ->and($sheet)->toContain('--only=guerriero-leila')
        ->and($sheet)->toContain('Julia Vera');

    Http::assertNothingSent();
});
