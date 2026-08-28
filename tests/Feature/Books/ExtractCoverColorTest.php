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

/**
 * GD's resampler does not land on the source colour to the byte, and JPEG
 * costs another point or two, so channels are compared with a tolerance
 * rather than the hex string.
 */
function expectColorNear(?string $color, string $expected): void
{
    expect($color)->toMatch('/^#[0-9a-f]{6}$/');

    foreach ([1, 3, 5] as $offset) {
        $channel = hexdec(substr((string)$color, $offset, 2));

        expect($channel)->toBeGreaterThanOrEqual(hexdec(substr($expected, $offset, 2)) - 8)
            ->and($channel)->toBeLessThanOrEqual(hexdec(substr($expected, $offset, 2)) + 8);
    }
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
 | a cover after the row is written. These cover the three ways the leading
 | image changes, all of them wired up in AppServiceProvider.
 */
it('stores the cover colour when a cover is attached', function(): void {
    $book = Book::factory()->create();

    attachCover($book, fakeCover());

    expectColorNear($book->fresh()->cover_color, '#c81e1e');
});

it('leaves the colour empty for a book with no cover', function(): void {
    expect(Book::factory()->create()->cover_color)->toBeNull();
});

it('recomputes the colour when another image is dragged to the front', function(): void {
    $book = Book::factory()->create();
    $red = attachCover($book, fakeCover(), 'roja.jpg');
    $twoTone = attachCover($book, twoToneCover(), 'dos-tonos.jpg');

    expectColorNear($book->fresh()->cover_color, '#c81e1e');

    Media::setNewOrder([$twoTone->id, $red->id]);

    expectColorNear($book->fresh()->cover_color, '#7f007f');
});

it('clears the colour when the last cover is deleted', function(): void {
    $book = Book::factory()->create();
    $cover = attachCover($book, fakeCover());

    expectColorNear($book->fresh()->cover_color, '#c81e1e');

    $cover->delete();

    expect($book->fresh()->cover_color)->toBeNull();
});

it('falls back to the next image when the cover is deleted', function(): void {
    $book = Book::factory()->create();
    $red = attachCover($book, fakeCover(), 'roja.jpg');
    attachCover($book, twoToneCover(), 'dos-tonos.jpg');

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
