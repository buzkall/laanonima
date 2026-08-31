<?php

use App\Support\CoverPalette;
use Tests\TestCase;

/*
 | The palette is read from config/site.php, so these need a booted
 | application. Bound here rather than in tests/Pest.php: the rest of
 | tests/Unit is free of the framework and should stay that way.
 */
uses(TestCase::class);

/**
 * WCAG 2.1 contrast between two colours, computed independently of the class
 * under test so the threshold is verified rather than restated.
 */
$contrast = function(string $first, string $second): float {
    $luminance = function(string $hex): float {
        [$red, $green, $blue] = array_map(
            function(int $channel): float {
                $srgb = $channel / 255;

                return $srgb <= 0.04045 ? $srgb / 12.92 : (($srgb + 0.055) / 1.055) ** 2.4;
            },
            sscanf($hex, '#%02x%02x%02x'),
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    };

    return (max($luminance($first), $luminance($second)) + 0.05)
        / (min($luminance($first), $luminance($second)) + 0.05);
};

it('paints a book in the colour read off its cover', function(): void {
    expect(CoverPalette::fromCover('#3A7B86')->background)->toBe('#3a7b86');
});

it('falls back to the house red when there is no colour to read', function(?string $stored): void {
    expect(CoverPalette::fromCover($stored)->background)->toBe(config('site.palette.fallback'));
})->with([
    'never derived'   => [null],
    'blank'           => [''],
    'shorthand'       => ['#fff'],
    'named'           => ['red'],
    'not hexadecimal' => ['#zzzzzz'],
]);

it('writes cream over a dark cover and ink over a pale one', function(): void {
    expect(CoverPalette::fromCover('#211511')->foreground)->toBe(config('site.palette.cream'))
        ->and(CoverPalette::fromCover('#ecb9bc')->foreground)->toBe(config('site.palette.ink'));
});

it('darkens the accent until it can be read on the cream page', function(string $cover) use ($contrast): void {
    expect($contrast(CoverPalette::fromCover($cover)->accent, config('site.palette.paper')))
        ->toBeGreaterThanOrEqual(4.5);
})->with([
    'a washed pink' => ['#ecb9bc'],
    'a pale beige'  => ['#ccb8aa'],
    'a mid teal'    => ['#3a7b86'],
    'white'         => ['#ffffff'],
]);

it('keeps the hue while darkening, rather than reaching for grey', function(): void {
    [$red, $green, $blue] = sscanf(CoverPalette::fromCover('#ecb9bc')->accent, '#%02x%02x%02x');

    expect($red)->toBeGreaterThan($green)
        ->and($red)->toBeGreaterThan($blue);
});

it('leaves a colour that already reads on the cream page alone', function(): void {
    expect(CoverPalette::fromCover('#211511')->accent)->toBe('#211511');
});

it('fades the foreground for rules drawn over the cover colour', function(): void {
    expect(CoverPalette::fromCover('#211511')->foregroundFaded())
        ->toBe('color-mix(in srgb, ' . config('site.palette.cream') . ' 45%, transparent)');
});
