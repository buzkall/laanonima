<?php

use App\Actions\Books\AttachBookCover;
use App\Enums\BookCoverOutcome;
use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Models\Book;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * A book image as the panel uploads one, sized past the cover floor.
 */
function bookImage(string $name): UploadedFile
{
    return UploadedFile::fake()->image($name, 600, 900);
}

it('keeps every image a book is given, in the order they were uploaded', function(): void {
    $book = Book::factory()->create();

    livewire(EditBook::class, ['record' => $book->getRouteKey()])
        ->fillForm(['covers' => [
            bookImage('cubierta.jpg'),
            bookImage('contracubierta.jpg'),
            bookImage('lomo.jpg'),
        ]])
        ->call('save')
        ->assertHasNoFormErrors();

    $images = $book->refresh()->getMedia(Book::COVERS_COLLECTION);

    expect($images)->toHaveCount(3)
        ->and($book->cover()->id)->toBe($images->first()->id);

    /* Uploads land on the media library's disk, not Filament's default one. */
    $images->each(fn($image) => Storage::disk('public')->assertExists($image->getPathRelativeToRoot()));
});

it('treats the first image in the collection as the cover', function(): void {
    $book = Book::factory()->create();
    $first = $book->addMediaFromString(fakeCover())->usingFileName('primera.jpg')->toMediaCollection(Book::COVERS_COLLECTION);
    $second = $book->addMediaFromString(fakeCover())->usingFileName('segunda.jpg')->toMediaCollection(Book::COVERS_COLLECTION);

    expect($book->refresh()->cover()->file_name)->toBe('primera.jpg');

    /* Reordering is how a bookseller promotes another image to the cover. */
    Media::setNewOrder([$second->id, $first->id]);

    expect($book->refresh()->cover()->file_name)->toBe('segunda.jpg');
});

it('generates a thumbnail so the listing does not serve the original', function(): void {
    $book = Book::factory()->create();
    $book->addCoverFromString(fakeCover(800, 1200));

    $cover = $book->refresh()->cover();

    expect($cover->hasGeneratedConversion('thumb'))->toBeTrue();
    Storage::disk('public')->assertExists($cover->getPathRelativeToRoot('thumb'));
});

it('shows the cover in the listing', function(): void {
    $book = Book::factory()->create();
    $book->addCoverFromString(fakeCover());

    livewire(ListBooks::class)
        ->assertTableColumnStateSet('cover', [$book->cover()->uuid], $book);
});

it('deletes the images along with the book', function(): void {
    $book = Book::factory()->create();
    $cover = $book->addCoverFromString(fakeCover());

    $book->delete();

    Storage::disk('public')->assertMissing("{$cover->id}/{$cover->file_name}");
});

describe('the cover the ISBN lookup finds', function(): void {
    beforeEach(function(): void {
        config()->set('books.metadata.google_books.key');

        Http::fake([
            'openlibrary.org/api/books*' => Http::response(apiFixture('book-metadata/open-library-hit')),
            'covers.openlibrary.org/*'   => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg']),
        ]);
    });

    /*
     | The lookup runs on a create page, where there is no record yet and so no
     | media collection to add to. It only records the source URL; the download
     | happens once the book has an id.
     */
    it('is attached once the new book exists', function(): void {
        livewire(CreateBook::class)
            ->fillForm(['isbn13' => '9788433920423'])
            ->callFormComponentAction('isbn13', 'lookup')
            ->call('create')
            ->assertHasNoFormErrors();

        $book = Book::firstWhere('isbn13', '9788433920423');

        expect($book->cover())->not->toBeNull()
            ->and($book->cover()->file_name)->toBe('9788433920423.jpg');
    });

    it('never overrules an image the bookseller uploaded', function(): void {
        livewire(CreateBook::class)
            ->fillForm([
                'isbn13' => '9788433920423',
                'covers' => [bookImage('mia.jpg')],
            ])
            ->callFormComponentAction('isbn13', 'lookup')
            ->call('create')
            ->assertHasNoFormErrors();

        $book = Book::firstWhere('isbn13', '9788433920423');

        expect($book->getMedia(Book::COVERS_COLLECTION))->toHaveCount(1)
            ->and($book->cover()->file_name)->not->toBe('9788433920423.jpg');
    });

    it('is attached when the lookup is run on an existing book', function(): void {
        $book = Book::factory()->create(['isbn13' => '9788433920423']);

        livewire(EditBook::class, ['record' => $book->getRouteKey()])
            ->callFormComponentAction('isbn13', 'lookup')
            ->call('save')
            ->assertHasNoFormErrors();

        expect($book->refresh()->cover())->not->toBeNull();
    });

    /*
     | Otherwise deleting an image and saving would bring it straight back, for
     | as long as cover_source_url stayed on the record.
     */
    it('does not come back on a later save once it has been deleted', function(): void {
        $book = Book::factory()->create([
            'isbn13'           => '9788433920423',
            'cover_source_url' => 'https://covers.openlibrary.org/b/id/1.jpg',
        ]);

        livewire(EditBook::class, ['record' => $book->getRouteKey()])
            ->fillForm(['title' => 'Título corregido'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($book->refresh()->cover())->toBeNull();
    });
});

describe('downloading a cover from an address', function(): void {
    it('attaches it to the book there and then', function(): void {
        Http::fake(['*' => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg'])]);

        $book = Book::factory()->create(['isbn13' => '9788433920423']);

        livewire(EditBook::class, ['record' => $book->getRouteKey()])
            ->callFormComponentAction('covers', 'downloadCover', [
                'url' => 'https://imagessl3.casadellibro.com/a/l/t0/23/9788433920423.jpg',
            ])
            ->assertNotified();

        expect($book->refresh()->cover())->not->toBeNull()
            ->and($book->cover_source_url)->toBe('https://imagessl3.casadellibro.com/a/l/t0/23/9788433920423.jpg');
    });

    it('refuses an address that is not an accepted source', function(): void {
        Http::fake(['*' => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg'])]);

        $book = Book::factory()->create();

        livewire(EditBook::class, ['record' => $book->getRouteKey()])
            ->callFormComponentAction('covers', 'downloadCover', ['url' => 'https://evil.example/cover.jpg'])
            ->assertNotified();

        expect($book->refresh()->cover())->toBeNull();
        Http::assertNothingSent();
    });

    /*
     | On a create page there is no record to attach to, so the address is held
     | on the form and AttachBookCover fetches it once the book has an id.
     */
    it('waits for the save when the book does not exist yet', function(): void {
        Http::fake(['*' => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg'])]);

        livewire(CreateBook::class)
            ->fillForm([
                'isbn13'       => '9788433920423',
                'title'        => 'A mano',
                'contributors' => [['name' => 'Alguien', 'role' => 'author']],
            ])
            ->callFormComponentAction('covers', 'downloadCover', [
                'url' => 'https://imagessl3.casadellibro.com/a/l/t0/23/9788433920423.jpg',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Book::firstWhere('isbn13', '9788433920423')->cover())->not->toBeNull();
    });
});

/*
 | The gap that made all of this necessary: Open Library answers with a record
 | but no cover for plenty of ISBNs, and a silent success looked like a bug.
 */
it('says so when the lookup found a record but no cover', function(): void {
    config()->set('books.metadata.google_books.key');

    Http::fake([
        'openlibrary.org/api/books*' => Http::response([
            'ISBN:9781846048548' => ['title' => 'Briefly Perfectly Human'],
        ]),
    ]);

    livewire(CreateBook::class)
        ->fillForm(['isbn13' => '9781846048548'])
        ->callFormComponentAction('isbn13', 'lookup')
        ->assertNotified(
            Notification::make()
                ->success()
                ->title(__('books.lookup.found_title'))
                ->body(__('books.lookup.found_body', ['title' => 'Briefly Perfectly Human'])
                    . ' ' . __('books.lookup.found_without_cover')),
        );
});

/*
 | Twice a bookseller added a book, got no cover and no explanation, and could
 | not tell a source with nothing to offer from a broken feature.
 */
describe('a cover that cannot be fetched after the save', function(): void {
    it('says so instead of failing quietly', function(): void {
        Http::fake(['*' => Http::response('<html>no</html>', 200, ['Content-Type' => 'image/jpeg'])]);

        $book = Book::factory()->create([
            'cover_source_url' => 'https://covers.openlibrary.org/b/id/1-L.jpg',
        ]);

        livewire(EditBook::class, ['record' => $book->getRouteKey()])
            ->fillForm(['cover_source_url' => 'https://covers.openlibrary.org/b/id/2-L.jpg'])
            ->call('save')
            ->assertNotified(
                Notification::make()
                    ->warning()
                    ->title(__('books.cover_download.failed_title'))
                    ->body(__('books.cover_download.failed_after_save'))
                    ->persistent(),
            );

        expect($book->refresh()->cover())->toBeNull();
    });

    it('stays quiet for a book that never named a source', function(): void {
        $book = Book::factory()->create(['cover_source_url' => null]);

        expect(app(AttachBookCover::class)($book))->toBe(BookCoverOutcome::Skipped);
    });

    it('stays quiet for a book that already has images', function(): void {
        $book = Book::factory()->create([
            'cover_source_url' => 'https://covers.openlibrary.org/b/id/1-L.jpg',
        ]);
        $book->addCoverFromString(fakeCover());

        expect(app(AttachBookCover::class)($book))->toBe(BookCoverOutcome::Skipped);
    });
});

describe('the cover colour', function(): void {
    it('keeps the colour the bookseller picks, in lowercase', function(): void {
        $book = Book::factory()->create();

        livewire(EditBook::class, ['record' => $book->getRouteKey()])
            ->fillForm(['cover_color' => '#3A7B86'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($book->refresh()->cover_color)->toBe('#3a7b86');
    });

    it('rejects anything that is not a hex triplet', function(): void {
        $book = Book::factory()->create(['cover_color' => '#3a7b86']);

        livewire(EditBook::class, ['record' => $book->getRouteKey()])
            ->fillForm(['cover_color' => 'rojo'])
            ->call('save')
            ->assertHasFormErrors(['cover_color']);

        expect($book->refresh()->cover_color)->toBe('#3a7b86');
    });

    /*
     | Nothing else reads the cover once a colour is stored, so this action is
     | the only way back to it.
     */
    it('reads the colour off the cover again when the bookseller asks', function(): void {
        $book = Book::factory()->create(['cover_color' => '#3a7b86']);
        $book->addCoverFromString(fakeCover());

        livewire(EditBook::class, ['record' => $book->getRouteKey()])
            ->callFormComponentAction('cover_color', 'resetCoverColor')
            ->call('save')
            ->assertHasNoFormErrors();

        expectColorNear($book->refresh()->cover_color, '#c81e1e');
    });
});
