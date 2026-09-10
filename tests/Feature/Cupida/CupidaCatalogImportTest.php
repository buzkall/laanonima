<?php

use App\Actions\Books\EnrichImportedBook;
use App\Actions\Books\FetchBookMetadata;
use App\Actions\Books\ImportShopBook;
use App\Actions\Cupida\RecommendBook;
use App\Enums\BookAvailability;
use App\Enums\ContributorRole;
use App\Livewire\Cupida;
use App\Models\Author;
use App\Models\Book;
use App\Models\CupidaRecommendation;
use App\Models\Publisher;
use App\Models\Subject;
use App\Support\CoverPalette;
use App\Support\Cupida\CupidaCatalog;
use App\Support\Cupida\CupidaLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    useCupidaFixture();

    config(['ai.providers.anthropic.key' => null]);

    /* Filing a book is the half that must cost nothing. Anything here that
       reaches for the network is the bug this guards. */
    Http::preventStrayRequests();
});

/**
 * One book out of the fixture pool, by EAN.
 *
 * @return array<string, mixed>
 */
function poolEntry(string $ean): array
{
    return app(CupidaCatalog::class)->book($ean);
}

it('files a book out of the shop pool without asking anything', function(): void {
    $book = app(ImportShopBook::class)(poolEntry('9788433922069'));

    expect($book)->toBeInstanceOf(Book::class)
        ->and($book->isbn13)->toBe('9788433922069')
        ->and($book->ean13)->toBe('9788433922069')
        ->and($book->title)->toBe('La llamada')
        ->and($book->price_cents)->toBe(2290)
        ->and($book->is_active)->toBeTrue()
        ->and($book->metadata_source)->toBe(ImportShopBook::SOURCE)
        ->and($book->synopsis)->toContain('dictadura')
        ->and($book->slug)->toBe('la-llamada-9788433922069')
        ->and($book->external_reference)->toBe(config('cupida.scrape.base_url') . '/libros/9788433922069/la-llamada/');
});

it('files the publisher and the author the listing names', function(): void {
    $book = app(ImportShopBook::class)(poolEntry('9788433922069'));

    expect($book->publisher?->name)->toBe('Anagrama')
        ->and(Publisher::query()->count())->toBe(1);

    /* Read the way a person writes it, not the way a listing sorts it. */
    expect($book->authors_line)->toBe('Leila Guerriero')
        ->and($book->contributors)->toHaveCount(1)
        ->and($book->contributors->first()->role)->toBe(ContributorRole::Author);
});

it('says the book is in stock when the shop has it on the shelf', function(): void {
    /* The book page decides its call to action on `stock`, so a zero here would
       greet a reader we have just sent there with the request form -- the exact
       hand-off this whole change removes. */
    $available = app(ImportShopBook::class)(poolEntry('9788433922069'));
    $sold = app(ImportShopBook::class)(poolEntry('9788419951144'));

    expect($available->stock)->toBe(1)
        ->and($available->availability)->toBe(BookAvailability::Available)
        ->and($sold->stock)->toBe(0)
        ->and($sold->availability)->toBe(BookAvailability::OutOfStock);
});

it('files the same book once however often it is recommended', function(): void {
    $first = app(ImportShopBook::class)(poolEntry('9788433922069'));
    $second = app(ImportShopBook::class)(poolEntry('9788433922069'));

    expect($second->is($first))->toBeTrue()
        ->and(Book::query()->count())->toBe(1)
        ->and($first->fresh()->contributors)->toHaveCount(1);
});

it('recognises a book we already filed under its barcode', function(): void {
    $ours = Book::factory()->create([
        'isbn13' => '9780000000002',
        'ean13'  => '9788433922069',
    ]);

    expect(app(ImportShopBook::class)(poolEntry('9788433922069'))?->is($ours))->toBeTrue()
        ->and(Book::query()->count())->toBe(1);
});

it('leaves a book a bookseller has taken off the web alone', function(): void {
    /* The row still owns the unique isbn13, so a lookup that could not see it
       would try to insert a second one and fail inside a reader's request. And
       an unpublished book is a 404: the reader belongs on the shop's page. */
    Book::factory()->create(['isbn13' => '9788433922069', 'is_active' => false]);

    expect(app(ImportShopBook::class)(poolEntry('9788433922069')))->toBeNull()
        ->and(Book::query()->count())->toBe(1);
});

it('files the most specific subject the shop gave us', function(): void {
    $broad = Subject::factory()->create(['code' => 'JB', 'name' => 'Sociedad y cultura']);
    $narrow = Subject::factory()->create(['code' => 'JBSF', 'name' => 'Feminismos', 'parent_id' => $broad->id]);

    $book = app(ImportShopBook::class)(poolEntry('9788410249516'));

    expect($book->subject_id)->toBe($narrow->id);
});

it('walks a subject code down to one we do have', function(): void {
    /* The pool's codes come from the shop's tree and ours from a seed of it,
       and the two drift. A missing leaf should not put a book on the "sin
       materia" pile when its parent says which shelf it belongs on. */
    $broad = Subject::factory()->create(['code' => 'JB', 'name' => 'Sociedad y cultura']);

    expect(app(ImportShopBook::class)(poolEntry('9788410249516'))->subject_id)->toBe($broad->id);
});

it('files no subject when we have nothing the code fits under', function(): void {
    expect(app(ImportShopBook::class)(poolEntry('9788410249516'))->subject_id)->toBeNull();
});

it('never files a collective byline as an author', function(): void {
    /* "Vv. Aa." is not a person, and an Author row for it would earn a page of
       its own at /autor/vv-aa. */
    $book = app(ImportShopBook::class)([
        ...poolEntry('9788433922069'),
        'ean'    => '9788400000001',
        'author' => 'Vv. Aa.',
    ]);

    expect($book->contributors)->toBeEmpty()
        ->and(Author::query()->count())->toBe(0);
});

it('files a barcode that is not a valid isbn', function(): void {
    $book = app(ImportShopBook::class)([...poolEntry('9788433922069'), 'ean' => '8412345678903']);

    expect($book->isbn13)->toBe('8412345678903')
        ->and($book->ean13)->toBe('8412345678903');
});

describe('enriching what was filed', function(): void {
    beforeEach(function(): void {
        Storage::fake('public');
        Cache::flush();
    });

    it('fills the blanks the shop listing left without touching what it wrote', function(): void {
        $book = app(ImportShopBook::class)(poolEntry('9788433922069'));

        Http::fake([
            'openlibrary.org/api/books*' => Http::response(['ISBN:9788433922069' => [
                'title'           => 'The Call',
                'number_of_pages' => 224,
                'identifiers'     => ['isbn_10' => ['8433922068']],
                'publish_date'    => '2024',
            ]]),
            'openlibrary.org/isbn/*' => Http::response(['physical_dimensions' => '20 x 13 x 2 centimeters']),
            '*'                      => Http::response(fakeCover()),
        ]);

        app(EnrichImportedBook::class)($book);

        $book->refresh();

        expect($book->pages)->toBe(224)
            ->and($book->isbn10)->toBe('8433922068')
            ->and($book->height_mm)->toBe(200)
            ->and($book->metadata_synced_at)->not->toBeNull()
            /* The shop's own record of its own book wins. A provider's English
               title and our filter's handle on these rows both survive. */
            ->and($book->title)->toBe('La llamada')
            ->and($book->price_cents)->toBe(2290)
            ->and($book->metadata_source)->toBe(ImportShopBook::SOURCE);
    });

    it('takes the cover the shop has when no source has one of its own', function(): void {
        $book = app(ImportShopBook::class)(poolEntry('9788433922069'));

        Http::fake([
            'openlibrary.org/*'                               => Http::response([], 404),
            'imagessl3.casadellibro.com/*'                    => Http::response([], 404),
            config('cupida.scrape.base_url') . '/imagen.php*' => Http::response(fakeCover()),
        ]);

        app(EnrichImportedBook::class)($book);

        expect($book->fresh()->hasMedia(Book::COVERS_COLLECTION))->toBeTrue()
            ->and($book->fresh()->cover_color)->toMatch('/^#[0-9a-f]{6}$/');
    });

    it('leaves a usable book behind when every source is down', function(): void {
        $book = app(ImportShopBook::class)(poolEntry('9788433922069'));

        Http::fake(fn() => Http::response('', 500));

        app(EnrichImportedBook::class)($book);

        expect($book->fresh()->title)->toBe('La llamada')
            ->and($book->fresh()->hasMedia(Book::COVERS_COLLECTION))->toBeFalse();
    });

    it('does not ask a metadata source about a barcode that is not an isbn', function(): void {
        $book = Book::factory()->create(['isbn13' => '8412345678903', 'cover_source_url' => null]);

        $this->mock(FetchBookMetadata::class)->shouldNotReceive('__invoke');

        Http::fake(fn() => Http::response(fakeCover()));

        app(EnrichImportedBook::class)($book);

        /* Stamped all the same: the column says we looked, so a book no source
           knows is not picked up again on every sweep. */
        expect($book->fresh()->metadata_synced_at)->not->toBeNull();
    });
});

it('puts the book it filed on the logged recommendation', function(): void {
    $recommendation = app(RecommendBook::class)(likes: ['theme:FM'], passes: []);

    $row = CupidaRecommendation::query()->sole();

    expect($row->book_id)->toBe($recommendation->book->id)
        ->and(app(CupidaLog::class)->session($row)['recommended']['in_catalog'])->toBeTrue();
});

it('still hands back a book when filing one blows up', function(): void {
    Log::spy();

    $this->mock(ImportShopBook::class)
        ->shouldReceive('__invoke')
        ->andThrow(new RuntimeException('disk on fire'));

    $recommendation = app(RecommendBook::class)(likes: ['theme:FM'], passes: []);

    expect($recommendation->book)->toBeNull()
        ->and($recommendation->url)->toBe($recommendation->shopUrl);

    Log::shouldHaveReceived('warning')->withArgs(
        fn(string $message): bool => str_contains($message, 'could not file'),
    )->once();
});

it('stops filing books once the day has had its share', function(): void {
    config()->set('cupida.import.daily_cap', 2);

    Book::factory()->count(2)->create(['metadata_source' => ImportShopBook::SOURCE]);

    $recommendation = app(RecommendBook::class)(likes: ['theme:FM'], passes: []);

    expect($recommendation->book)->toBeNull()
        ->and(Book::query()->where('metadata_source', ImportShopBook::SOURCE)->count())->toBe(2);
});

/**
 * Swipe all the way through to the one book, the way a reader does.
 */
function swipeToResult(): Testable
{
    $component = livewire(Cupida::class)->call('start');

    foreach (range(0, 3) as $round) {
        foreach ($component->viewData('cards') as $card) {
            $component->call('swipe', $card->answer(), true);
        }
    }

    return $component->call('recommend');
}

it('draws our page as the button and the shop as the line under it', function(): void {
    $component = swipeToResult();

    $book = Book::query()->where('metadata_source', ImportShopBook::SOURCE)->sole();

    $component
        ->assertSee(route('books.show', $book), escape: false)
        ->assertSee(__('cupida.result.read_more'))
        ->assertSee(__('cupida.result.at_the_shop'))
        ->assertSee(config('cupida.scrape.base_url') . '/libros/' . $book->isbn13 . '/', escape: false);
});

it('offers only the shop when the book could not be filed', function(): void {
    config()->set('cupida.import.enabled', false);

    swipeToResult()
        ->assertSee(__('cupida.result.buy'))
        ->assertDontSee(__('cupida.result.at_the_shop'));

    expect(Book::query()->count())->toBe(0);
});

it('keeps the artwork the panel opened with', function(): void {
    /* A book filed a moment ago has no cover of its own yet, so the panel goes
       on drawing the shop's image and the fallback color. It has to: this panel
       is rendered once, and a repaint mid-paragraph is the reader's page moving
       under them. */
    $component = swipeToResult();

    $book = Book::query()->where('metadata_source', ImportShopBook::SOURCE)->sole();

    expect($book->cover_color)->toBeNull();

    $component
        ->assertSee(config('cupida.scrape.base_url') . '/imagen.php?ean=' . $book->isbn13 . '&amp;ancho=400', escape: false)
        ->assertSee('--card: ' . CoverPalette::fromCover(null)->background, escape: false);
});

describe('the catch-up command', function(): void {
    beforeEach(function(): void {
        Storage::fake('public');
        Cache::flush();
        Http::fake(fn() => Http::response(fakeCover()));
    });

    it('finishes the books the deferred pass never got to', function(): void {
        /* Deferring the lookup is right and is also the part that can quietly
           not happen -- a closed tab, a hung provider, a process out of time.
           The row is already correct either way; this is what fetches the
           cover afterwards. */
        $book = app(ImportShopBook::class)(poolEntry('9788433922069'));

        expect($book->metadata_synced_at)->toBeNull();

        $this->artisan('books:enrich')->assertSuccessful();

        expect($book->fresh()->metadata_synced_at)->not->toBeNull()
            ->and($book->fresh()->hasMedia(Book::COVERS_COLLECTION))->toBeTrue();
    });

    it('leaves a book it has already been through alone', function(): void {
        app(ImportShopBook::class)(poolEntry('9788433922069'));

        $this->artisan('books:enrich')->assertSuccessful();
        $this->artisan('books:enrich')->expectsOutputToContain('Nothing left to enrich.');
    });

    it('does not keep picking up a book no source knows', function(): void {
        /* Most of these are recent Spanish titles the free sources have never
           heard of. Marking only the hits would make every sweep re-ask the
           same questions and get the same silence. */
        Http::fake(fn() => Http::response([], 404));

        app(ImportShopBook::class)(poolEntry('9788433922069'));

        $this->artisan('books:enrich')->assertSuccessful();
        $this->artisan('books:enrich')->expectsOutputToContain('Nothing left to enrich.');
    });

    it('never touches a book a bookseller entered by hand', function(): void {
        $theirs = Book::factory()->create(['metadata_source' => 'manual', 'metadata_synced_at' => null]);

        $this->artisan('books:enrich')->assertSuccessful();

        expect($theirs->fresh()->metadata_synced_at)->toBeNull();
    });
});
