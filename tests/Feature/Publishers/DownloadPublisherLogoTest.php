<?php

use App\Actions\Publishers\DownloadPublisherLogo;
use App\Enums\LogoOrigin;
use Illuminate\Support\Facades\Http;

/** The real shape of the Commons thumbnail for an SVG logotype. */
const NORMA_LOGO_THUMB = 'https://thumb.wikimedia.org/wikipedia/commons/thumb/8/8c/Norma_Editorial.svg/960px-Norma_Editorial.svg.png';

beforeEach(function(): void {
    Http::preventStrayRequests();
});

it('keeps the transparent background of a logotype', function(): void {
    Http::fake(['thumb.wikimedia.org/*' => Http::response(fakeLogo(400, 200))]);

    $bytes = (string)app(DownloadPublisherLogo::class)(NORMA_LOGO_THUMB, LogoOrigin::Wikidata, 'norma-editorial');

    $corner = imagecolorat(imagecreatefromstring($bytes), 0, 0);

    expect(getimagesizefromstring($bytes)[2])->toBe(IMAGETYPE_PNG)
        ->and(($corner >> 24) & 0x7F)->toBe(127);
});

it('hands back a truecolor png whatever the source served', function(): void {
    $palette = imagecreate(300, 150);
    imagecolorallocate($palette, 200, 30, 30);
    ob_start();
    imagegif($palette);
    Http::fake(['thumb.wikimedia.org/*' => Http::response((string)ob_get_clean())]);

    $bytes = (string)app(DownloadPublisherLogo::class)(NORMA_LOGO_THUMB, LogoOrigin::Wikidata, 'norma-editorial');

    expect(getimagesizefromstring($bytes)[2])->toBe(IMAGETYPE_PNG)
        ->and(imageistruecolor(imagecreatefromstring($bytes)))->toBeTrue();
});

it('scales a logotype down to fit and never up', function(int $width, int $height, array $expected): void {
    Http::fake(['thumb.wikimedia.org/*' => Http::response(fakeLogo($width, $height))]);

    $bytes = (string)app(DownloadPublisherLogo::class)(NORMA_LOGO_THUMB, LogoOrigin::Wikidata, 'norma-editorial');

    expect(array_slice(getimagesizefromstring($bytes), 0, 2))->toBe($expected);
})->with([
    'wide and large' => [2000, 1000, [800, 400]],
    'small'          => [300, 300, [300, 300]],
]);

it('rejects what is not a usable raster logotype', function(Closure $body): void {
    Http::fake(['thumb.wikimedia.org/*' => Http::response($body())]);

    expect(app(DownloadPublisherLogo::class)(NORMA_LOGO_THUMB, LogoOrigin::Wikidata, 'norma-editorial'))->toBeNull();
})->with([
    'too small'              => fn(): string => fakeLogo(64, 64),
    'svg'                    => fn(): string => '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="200"></svg>',
    'ico'                    => fn(): string => "\x00\x00\x01\x00" . str_repeat("\x00", 64),
    'html served with a 200' => fn(): string => '<!DOCTYPE html><html><body>No encontrado</body></html>',
]);

it('rejects a logotype over the size cap', function(): void {
    config()->set('publishers.logos.max_bytes', 100);
    Http::fake(['thumb.wikimedia.org/*' => Http::response(fakeLogo(400, 200))]);

    expect(app(DownloadPublisherLogo::class)(NORMA_LOGO_THUMB, LogoOrigin::Wikidata, 'norma-editorial'))->toBeNull();
});

it('refuses a wikidata logotype off the wikimedia hosts', function(): void {
    Http::fake();

    expect(app(DownloadPublisherLogo::class)('https://editorial.example/logo.png', LogoOrigin::Wikidata, 'norma-editorial'))->toBeNull();

    Http::assertNothingSent();
});

it('refuses a website icon on a private host', function(): void {
    fakeHosts(['intranet.example' => ['10.0.0.5']]);
    Http::fake();

    expect(app(DownloadPublisherLogo::class)('https://intranet.example/icon.png', LogoOrigin::Website, 'norma-editorial'))->toBeNull();

    Http::assertNothingSent();
});
