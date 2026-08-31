<?php

use App\Actions\Books\ExtractCoverColor;
use App\Models\Book;
use App\Models\Publisher;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function(): void {
    Storage::fake('public');
});

/** The action under test, which is invokable. */
function extractCoverColor(): ExtractCoverColor
{
    return new ExtractCoverColor;
}

/** A JPEG split into two halves, to prove the whole cover is averaged. */
function twoToneCover(int $width = 800, int $height = 1200): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width - 1, intdiv($height, 2) - 1, imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, 0, intdiv($height, 2), $width - 1, $height - 1, imagecolorallocate($image, 0, 0, 255));

    ob_start();
    imagejpeg($image);

    return (string)ob_get_clean();
}

/** Attach an image to a book's covers collection and hand back the media. */
function attachCover(Book $book, string $contents, string $fileName = 'cubierta.jpg'): Media
{
    return $book->addMediaFromString($contents)
        ->usingFileName($fileName)
        ->toMediaCollection(Book::COVERS_COLLECTION);
}

it('reads the dominant colour off a stored cover', function(): void {
    $cover = attachCover(Book::factory()->create(), fakeCover());

    expectColorNear(extractCoverColor()($cover), '#c81e1e');
});

it('averages a cover that is more than one colour', function(): void {
    $cover = attachCover(Book::factory()->create(), twoToneCover());

    expectColorNear(extractCoverColor()($cover), '#7f007f');
});

it('averages a transparent cover towards white rather than black', function(): void {
    $png = (function(): string {
        $image = imagecreatetruecolor(400, 600);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        ob_start();
        imagepng($image);

        return (string)ob_get_clean();
    })();

    $cover = attachCover(Book::factory()->create(), $png, 'transparente.png');

    expectColorNear(extractCoverColor()($cover), '#ffffff');
});

it('returns null rather than throwing when there is nothing to read', function(): void {
    expect(extractCoverColor()(null))->toBeNull();
});

it('returns null when the file behind the media is gone', function(): void {
    $cover = attachCover(Book::factory()->create(), fakeCover());

    Storage::disk('public')->delete($cover->getPathRelativeToRoot());

    expect(extractCoverColor()($cover))->toBeNull();
});

it('returns null when the file is not an image', function(): void {
    $cover = attachCover(Book::factory()->create(), fakeCover());

    Storage::disk('public')->put($cover->getPathRelativeToRoot(), 'plain text');

    expect(extractCoverColor()($cover))->toBeNull();
});

/*
 | The colour cannot be derived while the book is saved: media library attaches
 | a cover after the row is written. The trigger is the media itself, wired up
 | in AppServiceProvider -- but only ever to fill an empty column.
 */
it('stores the cover colour when a cover is attached', function(): void {
    $book = Book::factory()->create();

    attachCover($book, fakeCover());

    expectColorNear($book->fresh()->cover_color, '#c81e1e');
});

it('leaves the colour empty for a book with no cover', function(): void {
    expect(Book::factory()->create()->cover_color)->toBeNull();
});

/*
 | The colour on the record is the one the page is painted with, whether it was
 | read off a cover or chosen in the panel: nothing here tells the two apart,
 | and nothing here writes over either.
 */
it('keeps the colour it has when another image is dragged to the front', function(): void {
    $book = Book::factory()->create();
    $red = attachCover($book, fakeCover(), 'roja.jpg');
    $twoTone = attachCover($book, twoToneCover(), 'dos-tonos.jpg');

    expectColorNear($book->fresh()->cover_color, '#c81e1e');

    Media::setNewOrder([$twoTone->id, $red->id]);

    $reordered = $book->fresh();

    /* The two-tone image leads now, and the red one's colour has stayed. */
    expect($reordered->cover()->id)->toBe($twoTone->id);
    expectColorNear($reordered->cover_color, '#c81e1e');
});

it('keeps the colour it has when the last cover is deleted', function(): void {
    $book = Book::factory()->create();
    $cover = attachCover($book, fakeCover());

    expectColorNear($book->fresh()->cover_color, '#c81e1e');

    $cover->delete();

    expectColorNear($book->fresh()->cover_color, '#c81e1e');
});

it('keeps a colour that was never read off a cover at all', function(): void {
    $book = Book::factory()->create(['cover_color' => '#3a7b86']);

    attachCover($book, fakeCover());

    expect($book->fresh()->cover_color)->toBe('#3a7b86');
});

/*
 | Emptying the column is how a bookseller asks for the cover to be read again,
 | so both of the triggers still have work to do.
 */
it('reads the next image added once the colour is emptied', function(): void {
    $book = Book::factory()->create();
    attachCover($book, fakeCover(), 'roja.jpg');

    $book->update(['cover_color' => null]);
    attachCover($book, twoToneCover(), 'dos-tonos.jpg');

    /* The colour of the cover, which is still the first image, not of the one
       that was just added. */
    expectColorNear($book->fresh()->cover_color, '#c81e1e');
});

it('falls back to the next image when a cover is deleted and no colour is stored', function(): void {
    $book = Book::factory()->create();
    $red = attachCover($book, fakeCover(), 'roja.jpg');
    attachCover($book, twoToneCover(), 'dos-tonos.jpg');

    $book->update(['cover_color' => null]);
    $red->delete();

    expectColorNear($book->fresh()->cover_color, '#7f007f');
});

it('ignores media that is not a book cover', function(): void {
    $publisher = Publisher::factory()->create();

    $publisher->addMediaFromString(fakeCover())
        ->usingFileName('logo.jpg')
        ->toMediaCollection(Publisher::LOGO_COLLECTION);

    expect($publisher->fresh()->getFirstMedia(Publisher::LOGO_COLLECTION))->not->toBeNull();
});
