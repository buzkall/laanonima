<?php

use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Models\Book;
use App\Models\User;
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
        config()->set('books.metadata.google_books.key', null);

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
