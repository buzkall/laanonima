<?php

use function Pest\Laravel\get;

it('asks search engines not to index any response', function(string $url): void {
    get($url)->assertHeader('X-Robots-Tag', 'noindex');
})->with([
    'the shop'        => '/',
    'the admin panel' => '/admin/login',
    'a missing page'  => '/no-existe',
]);
