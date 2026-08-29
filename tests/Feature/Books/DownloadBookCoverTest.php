<?php

use App\Actions\Books\DownloadBookCover;
use Illuminate\Support\Facades\Http;

/** The action under test, which is invokable. */
function download(): DownloadBookCover
{
    return new DownloadBookCover;
}

it('downscales an oversized cover into the configured box', function() {
    Http::fake(['*' => Http::response(fakeCover(2000, 3207), 200, ['Content-Type' => 'image/jpeg'])]);

    $jpeg = download()('https://covers.openlibrary.org/cover.jpg', '9788433920423');

    [$width, $height] = getimagesizefromstring((string)$jpeg);

    expect($width)->toBe(748)
        ->and($height)->toBe(1200);
});

it('leaves a cover that already fits at its own size', function() {
    Http::fake(['*' => Http::response(fakeCover(563, 788), 200, ['Content-Type' => 'image/jpeg'])]);

    $jpeg = download()('https://covers.openlibrary.org/cover.jpg', '9788495587176');

    [$width, $height] = getimagesizefromstring((string)$jpeg);

    expect($width)->toBe(563)
        ->and($height)->toBe(788);
});

it('hands back jpeg bytes whatever the source served', function() {
    $gif = (function() {
        $image = imagecreatetruecolor(600, 900);
        ob_start();
        imagegif($image);

        return (string)ob_get_clean();
    })();

    Http::fake(['*' => Http::response($gif, 200, ['Content-Type' => 'image/gif'])]);

    $jpeg = download()('https://covers.openlibrary.org/cover.gif', '9788478887200');

    expect(getimagesizefromstring((string)$jpeg)[2])->toBe(IMAGETYPE_JPEG);
});

/*
 | The reason the guard exists: cegal answers an unknown ISBN with a 200 and a
 | cover-shaped 250x375 placeholder, so the status code proves nothing.
 */
it('rejects a cover-shaped placeholder served with a 200', function() {
    Http::fake(['*' => Http::response(fakeCover(250, 375), 200, ['Content-Type' => 'image/jpeg'])]);

    expect(download()('https://covers.openlibrary.org/cover.jpg', '9788433920423'))->toBeNull();
});

it('rejects a body that is not an image at all', function() {
    Http::fake(['*' => Http::response('<html>404 no encontrado</html>', 200, ['Content-Type' => 'image/jpeg'])]);

    expect(download()('https://covers.openlibrary.org/cover.jpg', '9788433920423'))->toBeNull();
});

it('keeps nothing when the source fails outright', function() {
    Http::fake(['*' => Http::response('', 500)]);

    expect(download()('https://covers.openlibrary.org/cover.jpg', '9788433920423'))->toBeNull();
});

/*
 | Cover URLs are read out of third-party API responses, so a provider that is
 | compromised or spoofed could otherwise aim the server at internal addresses.
 */
it('refuses a host that is not an allowed cover source', function() {
    Http::fake(['*' => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg'])]);

    expect(download()('https://evil.example/cover.jpg', '9788433920423'))->toBeNull();

    Http::assertNothingSent();
});

it('refuses to reach internal addresses', function() {
    Http::fake(['*' => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg'])]);

    foreach ([
        'https://127.0.0.1/cover.jpg',
        'https://169.254.169.254/latest/meta-data/iam/security-credentials/',
        'https://10.0.0.5/cover.jpg',
        'https://[::1]/cover.jpg',
        'https://localhost/cover.jpg',
    ] as $url) {
        expect(download()($url, '9788433920423'))->toBeNull("{$url} was not refused");
    }

    Http::assertNothingSent();
});

it('refuses a plain http url', function() {
    Http::fake(['*' => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg'])]);

    expect(download()('http://covers.openlibrary.org/cover.jpg', '9788433920423'))->toBeNull();

    Http::assertNothingSent();
});

it('allows the redirect target Open Library actually uses', function() {
    Http::fake(['*' => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg'])]);

    expect(download()('https://ia600404.us.archive.org/view_archive.php', '9788478887200'))
        ->not->toBeNull();
});
