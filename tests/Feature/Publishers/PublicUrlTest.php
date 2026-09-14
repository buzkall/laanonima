<?php

use App\Support\PublisherLogos\PublicUrl;
use GuzzleHttp\Psr7\Uri;

beforeEach(function(): void {
    fakeHosts([
        'editorial.example' => ['93.184.216.34'],
        'intranet.example'  => ['10.0.0.5'],
        'mixed.example'     => ['93.184.216.34', '127.0.0.1'],
    ]);
});

it('refuses a url that would reach our own network', function(string $url): void {
    expect(app(PublicUrl::class)->allowed($url))->toBeFalse()
        ->and(app(PublicUrl::class)->options($url))->toBeNull();
})->with([
    'plain http'                   => 'http://editorial.example/',
    'loopback'                     => 'https://127.0.0.1/',
    'cloud metadata'               => 'https://169.254.169.254/latest/meta-data/',
    'carrier-grade nat'            => 'https://100.64.0.1/',
    'ipv6 loopback'                => 'https://[::1]/',
    'ipv6 private'                 => 'https://[fc00::1]/',
    'loopback written as ipv6'     => 'https://[::ffff:127.0.0.1]/',
    'localhost'                    => 'https://localhost/',
    'local name'                   => 'https://printer.local/',
    'name on a private address'    => 'https://intranet.example/',
    'one of its addresses private' => 'https://mixed.example/',
    'name that does not resolve'   => 'https://nowhere.example/',
    'other port'                   => 'https://editorial.example:8443/',
    'credentials'                  => 'https://user:secret@editorial.example/',
]);

it('allows a host on a public address', function(): void {
    expect(app(PublicUrl::class)->allowed('https://editorial.example/logo.png'))->toBeTrue();
});

it('pins the request to the address it checked', function(): void {
    /* Without this, a name could resolve to a public address for the check
       and to a private one for the request. */
    $options = app(PublicUrl::class)->options('https://editorial.example/logo.png');

    expect($options['curl'][CURLOPT_RESOLVE])->toBe(['editorial.example:443:93.184.216.34']);
});

it('refuses a redirect into our own network', function(): void {
    $guard = app(PublicUrl::class)->options('https://editorial.example/logo.png')['allow_redirects']['on_redirect'];

    expect(fn() => $guard(null, null, new Uri('https://intranet.example/')))->toThrow(RuntimeException::class);
});
