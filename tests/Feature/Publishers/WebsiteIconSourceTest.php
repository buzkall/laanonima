<?php

use App\Support\PublisherLogos\WebsiteIconSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function(): void {
    Http::preventStrayRequests();
    fakeHosts([
        'blackiebooks.org'  => ['93.184.216.34'],
        'editorial.example' => ['93.184.216.34'],
        'intranet.example'  => ['10.0.0.5'],
    ]);
});

/** A home page with nothing in it but the head a test hands over. */
function homePage(string $head): string
{
    return "<!DOCTYPE html><html><head>{$head}</head><body></body></html>";
}

it('takes the touch icon first and never the svg logo or the share banner', function(): void {
    /* Blackie Books' real head: its JSON-LD logo is an SVG, and its og:image
       is a 4007x2001 banner. */
    Http::fake(['https://blackiebooks.org/' => Http::response(
        file_get_contents(fixture('publisher-logos/home-blackie.html')),
        200,
        ['Content-Type' => 'text/html; charset=UTF-8'],
    )]);

    expect(app(WebsiteIconSource::class)->candidates('https://blackiebooks.org/'))->toBe([
        'https://blackiebooks.org/wp-content/uploads/2024/03/cropped-favicon-2-180x180.png',
        'https://blackiebooks.org/wp-content/uploads/2024/03/cropped-favicon-2-192x192.png',
        'https://blackiebooks.org/wp-content/uploads/2024/03/cropped-favicon-2-32x32.png',
    ]);
});

it('prefers a declared logotype and resolves relative links against the page', function(): void {
    Http::fake(['https://editorial.example/' => Http::response(homePage(<<<'HTML'
        <link rel="icon" href="/favicon-32.png" sizes="32x32">
        <link rel="apple-touch-icon" href="touch.png">
        <script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"Organization","logo":{"@type":"ImageObject","url":"/img/logo.png"}}]}</script>
        HTML), 200, ['Content-Type' => 'text/html'])]);

    expect(app(WebsiteIconSource::class)->candidates('https://editorial.example/'))->toBe([
        'https://editorial.example/img/logo.png',
        'https://editorial.example/touch.png',
        'https://editorial.example/favicon-32.png',
    ]);
});

it('never offers an icon nothing here can decode', function(): void {
    Http::fake(['https://editorial.example/' => Http::response(homePage(<<<'HTML'
        <link rel="icon" type="image/svg+xml" href="/icon.svg">
        <link rel="shortcut icon" href="/favicon.ico">
        <link rel="mask-icon" href="/mask.png">
        <link rel="icon" href="/dark.png" media="(prefers-color-scheme: dark)">
        <link rel="icon" href="/any.png" sizes="any">
        <link rel="icon" href="/light.png" sizes="48x48">
        HTML), 200, ['Content-Type' => 'text/html'])]);

    expect(app(WebsiteIconSource::class)->candidates('https://editorial.example/'))
        ->toBe(['https://editorial.example/light.png']);
});

it('reads a website recorded over http from its home page over https', function(): void {
    Http::fake(['https://editorial.example/' => Http::response(homePage(''), 200, ['Content-Type' => 'text/html'])]);

    $candidates = app(WebsiteIconSource::class)->candidates('http://editorial.example/sobre-nosotros');

    expect($candidates)->toBe(['https://editorial.example/apple-touch-icon.png']);

    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://editorial.example/');
});

it('never asks a publishing group or a private host for an icon', function(string $website): void {
    Http::fake();

    expect(app(WebsiteIconSource::class)->candidates($website))->toBeEmpty();

    Http::assertNothingSent();
})->with([
    'publishing group' => 'https://www.penguinlibros.com/es/11335-taurus',
    'private host'     => 'https://intranet.example/',
]);
