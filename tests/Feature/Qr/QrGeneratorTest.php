<?php

use App\Support\Qr\QrGenerator;
use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Illuminate\Support\Str;
use Zxing\QrReader;

/**
 * Writes $contents to a unique temporary file and hands the path to $assert.
 * The decoder only reads from disk, and the suite runs in parallel, so a fixed
 * filename would be a race between workers.
 */
function withTemporaryImage(string $contents, string $extension, Closure $assert): void
{
    $path = sys_get_temp_dir() . '/qr-' . Str::uuid() . '.' . $extension;
    file_put_contents($path, $contents);

    try {
        $assert($path);
    } finally {
        unlink($path);
    }
}

it('produces a thermal PNG that still decodes with the logo on top', function(string $url): void {
    withTemporaryImage(app(QrGenerator::class)->thermalPng($url, 576), 'png', function(string $path) use ($url): void {
        expect((new QrReader($path))->text())->toBe($url);
    });
})->with([
    'corta' => 'https://laanonimalibreria.com',
    'larga' => 'https://laanonimalibreria.com/editorial-del-mes/anagrama?utm_source=ticket&utm_medium=qr&utm_campaign=septiembre',
]);

it('still decodes at the narrowest printer width, where modules are smallest', function(): void {
    $url = 'https://laanonimalibreria.com/editorial-del-mes/anagrama?utm_source=ticket&utm_medium=qr&utm_campaign=septiembre';

    withTemporaryImage(app(QrGenerator::class)->thermalPng($url, 384), 'png', function(string $path) use ($url): void {
        expect((new QrReader($path))->text())->toBe($url);
    });
});

it('produces a PNG exactly as wide as the printer asked for', function(int $width): void {
    // The printer's dot count is fixed, so anything but an exact match would be
    // rescaled downstream and blur the module edges.
    $info = getimagesizefromstring(app(QrGenerator::class)->thermalPng('https://example.com', $width));

    expect($info[0])->toBe($width)
        ->and($info[1])->toBe($width);
})->with([
    '58 mm' => 384,
    '80 mm' => 576,
]);

it('keeps the quiet zone at or above the configured minimum', function(int $width): void {
    $url = 'https://laanonimalibreria.com';

    $blockCount = (new MatrixFactory)->create(new QrCode(
        data: $url,
        errorCorrectionLevel: ErrorCorrectionLevel::High,
    ))->getBlockCount();

    $info = getimagesizefromstring(app(QrGenerator::class)->thermalPng($url, $width));
    $blockSize = intdiv($info[0], $blockCount + 2 * config('qr.quiet_modules'));
    $margin = ($info[0] - $blockCount * $blockSize) / 2;

    expect($margin / $blockSize)->toBeGreaterThanOrEqual(config('qr.quiet_modules'));
})->with([384, 576]);

it('composes an SVG that survives rasterising and decoding', function(): void {
    $url = 'https://laanonimalibreria.com/editorial-del-mes/anagrama?utm_source=ticket&utm_medium=qr&utm_campaign=septiembre';

    $image = new Imagick;
    $image->setBackgroundColor(new ImagickPixel('white'));
    $image->readImageBlob(app(QrGenerator::class)->svg($url, 512));
    $image->setImageFormat('png');

    withTemporaryImage($image->getImageBlob(), 'png', function(string $path) use ($url): void {
        expect((new QrReader($path))->text())->toBe($url);
    });
})->skip(
    fn(): bool => ! extension_loaded('imagick') || (new Imagick)->queryFormats('SVG') === [],
    'ImageMagick sin delegado SVG.',
);

it('refuses to build a code whose modules would collapse below a pixel', function(): void {
    // A long payload needs many modules; at a tiny width each would round down
    // to zero pixels, so this must fail loudly rather than emit a blank image.
    expect(fn() => app(QrGenerator::class)->thermalPng(
        'https://laanonimalibreria.com/editorial-del-mes/anagrama?utm_source=ticket&utm_medium=qr',
        40,
    ))->toThrow(RuntimeException::class);
});

it('draws the glyph on a white plate rather than a solid block', function(int $size): void {
    // Endroid's own logo support flattens our transparent-black asset into a
    // filled black rectangle, which still decodes — so decoding alone cannot
    // catch it. Measure the white left around the glyph instead.
    $image = imagecreatefromstring(app(QrGenerator::class)->thermalPng('https://laanonimalibreria.com', $size));

    [$logoWidth, $logoHeight] = getimagesize((string)config('qr.assets.png'));
    $side = $size * (float)config('qr.logo_ratio');
    $scale = min($side / $logoWidth, $side / $logoHeight);
    $padding = 2 * (int)round($side * (float)config('qr.logo_padding'));

    $plateWidth = (int)round($logoWidth * $scale) + $padding;
    $plateHeight = (int)round($logoHeight * $scale) + $padding;
    $left = intdiv($size - $plateWidth, 2);
    $top = intdiv($size - $plateHeight, 2);

    $white = 0;

    for ($x = $left; $x < $left + $plateWidth; $x++) {
        for ($y = $top; $y < $top + $plateHeight; $y++) {
            $colour = imagecolorsforindex($image, imagecolorat($image, $x, $y));

            if ($colour['red'] > 200 && $colour['green'] > 200 && $colour['blue'] > 200) {
                $white++;
            }
        }
    }

    expect($white / ($plateWidth * $plateHeight))->toBeGreaterThan(0.25);
})->with([384, 576]);

it('produces a fully opaque PNG so the glyph reads as ink', function(int $size): void {
    // Endroid quantises a logo-less code to a 16 colour palette, and a palette
    // canvas cannot blend the logo's alpha: the glyph came out transparent
    // instead of black, invisible on white and dark in a dark viewer.
    $png = app(QrGenerator::class)->thermalPng('https://laanonimalibreria.com', $size);
    $image = imagecreatefromstring($png);

    expect(imageistruecolor($image))->toBeTrue()
        ->and(ord(substr($png, 25, 1)))->toBe(2); // PNG colour type 2 = RGB, no alpha channel

    for ($x = 0; $x < $size; $x += 3) {
        for ($y = 0; $y < $size; $y += 3) {
            if (imagecolorsforindex($image, imagecolorat($image, $x, $y))['alpha'] !== 0) {
                throw new RuntimeException("Pixel transparente en {$x},{$y}.");
            }
        }
    }
})->with([384, 576]);

it('draws a glyph dark enough to survive a one bit printer', function(): void {
    $size = 576;
    $image = imagecreatefromstring(app(QrGenerator::class)->thermalPng('https://laanonimalibreria.com', $size));

    [$logoWidth, $logoHeight] = getimagesize((string)config('qr.assets.png'));
    $scale = min(
        $size * (float)config('qr.logo_ratio') / $logoWidth,
        $size * (float)config('qr.logo_ratio') / $logoHeight,
    );
    $drawnWidth = (int)round($logoWidth * $scale);
    $drawnHeight = (int)round($logoHeight * $scale);
    $left = intdiv($size - $drawnWidth, 2);
    $top = intdiv($size - $drawnHeight, 2);

    $dark = 0;

    for ($x = $left; $x < $left + $drawnWidth; $x++) {
        for ($y = $top; $y < $top + $drawnHeight; $y++) {
            if (imagecolorsforindex($image, imagecolorat($image, $x, $y))['red'] < 60) {
                $dark++;
            }
        }
    }

    // The glyph is a bold question mark, so it must cover a real share of its
    // own box. A transparent or missing glyph would leave this near zero.
    expect($dark / ($drawnWidth * $drawnHeight))->toBeGreaterThan(0.2);
});
