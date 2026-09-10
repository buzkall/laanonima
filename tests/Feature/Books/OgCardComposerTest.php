<?php

use App\Actions\Images\ComposeOgCard;
use App\Models\Book;
use App\Support\Og\OgCard;
use Illuminate\Support\Facades\Storage;

beforeEach(function(): void {
    Storage::fake('public');
});

/**
 * Read one pixel back out of a drawn card.
 */
function cardPixel(string $jpeg, int $x, int $y): string
{
    $image = imagecreatefromstring($jpeg);
    $color = imagecolorat($image, $x, $y);

    return sprintf('#%02x%02x%02x', ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF);
}

/**
 * The darkest pixel in the margin the type is never allowed to reach, as a
 * distance from the cream page. Anything that overflowed the column lands here.
 */
function cardMarginInk(string $jpeg): int
{
    $image = imagecreatefromstring($jpeg);
    [$r, $g, $b] = sscanf((string)config('site.palette.paper'), '#%02x%02x%02x');
    $worst = 0;

    for ($x = 1150; $x < 1200; $x++) {
        for ($y = 0; $y < 630; $y++) {
            $color = imagecolorat($image, $x, $y);

            $worst = max(
                $worst,
                abs((($color >> 16) & 0xFF) - $r)
                + abs((($color >> 8) & 0xFF) - $g)
                + abs(($color & 0xFF) - $b),
            );
        }
    }

    return $worst;
}

/*
 | The card is the page in miniature, and that is not only a likeness: the
 | title is drawn in the palette's accent, which CoverPalette derives by walking
 | the cover color towards black until it clears 4.5:1 against the paper. Paint
 | the band with anything else and that guarantee is gone.
 */
it('paints the card in the book own color', function(): void {
    $book = Book::factory()->create(['cover_color' => '#7b2d26']);

    $compose = new ComposeOgCard;
    $card = $compose(OgCard::forBook($book));

    /* Top-left corner of the band, which nothing is drawn over. */
    expectColorNear(cardPixel($card, 8, 8), '#7b2d26');

    /* And the cream page on the right, likewise untouched. */
    expectColorNear(cardPixel($card, 1192, 8), (string)config('site.palette.paper'));
});

it('shrinks a very long title rather than running it off the card', function(): void {
    $book = Book::factory()->create([
        'title'        => str_repeat('Título interminable ', 8),
        'contributors' => [['name' => 'Una Autora Con Un Nombre Muy Largo De Verdad', 'role' => 'author']],
    ]);

    $compose = new ComposeOgCard;
    $card = $compose(OgCard::forBook($book->fresh()));

    [$width, $height] = getimagesizefromstring($card);

    expect($width)->toBe(1200)->and($height)->toBe(630);

    /* Nothing reaches the margin past the text column. An unfitted subtitle
       ran its credit straight off the edge of the card, and a single sampled
       pixel was too easy to miss it with. */
    expect(cardMarginInk($card))->toBeLessThan(24);
});
