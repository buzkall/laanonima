<?php

use App\Support\Qr\QrGenerator;
use App\Support\Qr\SvgLogoComposer;
use Illuminate\Support\Str;

it('omits the XML prolog so the markup is safe to echo inline', function(): void {
    expect(app(QrGenerator::class)->svg('https://laanonimalibreria.com'))
        ->toStartWith('<svg');
});

it('produces well formed XML', function(): void {
    $document = new DOMDocument;

    expect(@$document->loadXML(app(QrGenerator::class)->svg('https://example.com')))->toBeTrue();
});

it('composes the logo as real vector shapes', function(): void {
    $document = new DOMDocument;
    $document->loadXML(app(QrGenerator::class)->svg('https://laanonimalibreria.com'));

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');

    // <circle> and <polygon> are the dot and the tilde of the isotipo. Endroid
    // only ever draws modules as <rect> or <path>, so these prove the logo was
    // composed in — unlike asserting on <path>, which the QR itself satisfies.
    expect($xpath->query('//svg:circle'))->toHaveCount(1)
        ->and($xpath->query('//svg:polygon'))->toHaveCount(1);
});

it('never rasterises the logo into an image element', function(): void {
    // An <image> would mean Endroid embedded the logo itself as base64 rather
    // than SvgLogoComposer drawing it, which loses the vector and lets modules
    // show through the glyph.
    expect(app(QrGenerator::class)->svg('https://example.com'))->not->toContain('<image');
});

it('renders identically under a comma decimal locale', function(): void {
    $reference = app(QrGenerator::class)->svg('https://example.com');

    setlocale(LC_NUMERIC, 'es_ES.UTF-8');

    try {
        // A locale-sensitive float format would slip commas into the transform
        // attributes and silently break the logo placement.
        expect(app(QrGenerator::class)->svg('https://example.com'))->toBe($reference);
    } finally {
        setlocale(LC_NUMERIC, 'C');
    }
})->skip(fn(): bool => setlocale(LC_NUMERIC, 'es_ES.UTF-8') === false, 'Locale es_ES no disponible.');

it('rejects a logo file that is not valid SVG', function(): void {
    $path = sys_get_temp_dir() . '/qr-not-svg-' . Str::uuid() . '.svg';
    file_put_contents($path, 'no soy un SVG');

    try {
        expect(fn(): string => (new SvgLogoComposer)->compose(
            app(QrGenerator::class)->svg('https://example.com'),
            $path,
        ))->toThrow(RuntimeException::class);
    } finally {
        unlink($path);
    }
});
