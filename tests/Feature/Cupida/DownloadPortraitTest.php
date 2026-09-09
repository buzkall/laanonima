<?php

use App\Actions\Portraits\DownloadPortrait;
use Illuminate\Support\Facades\Http;

/** The action under test, which is invokable. */
function downloadPortrait(): DownloadPortrait
{
    return new DownloadPortrait;
}

/** The real shape of a Commons thumbnail URL, host included. */
const COMMONS_THUMB = 'https://thumb.wikimedia.org/wikipedia/commons/thumb/9/93/Elvira_Sastre.jpg/800px-Elvira_Sastre.jpg';

it('crops a portrait source to exactly the card box', function(): void {
    Http::fake(['*' => Http::response(fakeCover(1200, 1600), 200, ['Content-Type' => 'image/jpeg'])]);

    [$width, $height] = getimagesizefromstring((string)downloadPortrait()(COMMONS_THUMB, 'sastre-elvira'));

    expect($width)->toBe(480)
        ->and($height)->toBe(640);
});

it('fills the box from a landscape source instead of letterboxing it', function(): void {
    /* The bug a `min` scale would hide. A cover is fitted into its box and may
       letterbox; a portrait has to cover its frame or the card shows a stripe
       of background down one side. */
    Http::fake(['*' => Http::response(fakeCover(1600, 900), 200, ['Content-Type' => 'image/jpeg'])]);

    [$width, $height] = getimagesizefromstring((string)downloadPortrait()(COMMONS_THUMB, 'alguien'));

    expect($width)->toBe(480)
        ->and($height)->toBe(640);
});

it('enlarges a source smaller than the box rather than refusing it', function(): void {
    Http::fake(['*' => Http::response(fakeCover(300, 400), 200, ['Content-Type' => 'image/jpeg'])]);

    [$width, $height] = getimagesizefromstring((string)downloadPortrait()(COMMONS_THUMB, 'alguien'));

    expect($width)->toBe(480)
        ->and($height)->toBe(640);
});

it('hands back jpeg bytes whatever the source served', function(): void {
    foreach (['imagegif', 'imagepng'] as $encoder) {
        $bytes = (function() use ($encoder): string {
            $image = imagecreatetruecolor(600, 900);
            ob_start();
            $encoder($image);

            return (string)ob_get_clean();
        })();

        Http::fake(['*' => Http::response($bytes, 200)]);

        expect(getimagesizefromstring((string)downloadPortrait()(COMMONS_THUMB, 'alguien'))[2])
            ->toBe(IMAGETYPE_JPEG, "{$encoder} did not come back as JPEG");
    }
});

it('rejects an image too small to hold a face', function(): void {
    Http::fake(['*' => Http::response(fakeCover(100, 100), 200, ['Content-Type' => 'image/jpeg'])]);

    expect(downloadPortrait()(COMMONS_THUMB, 'alguien'))->toBeNull();
});

it('rejects a placeholder served with a 200', function(): void {
    Http::fake(['*' => Http::response('<html>404</html>', 200, ['Content-Type' => 'text/html'])]);

    expect(downloadPortrait()(COMMONS_THUMB, 'alguien'))->toBeNull();
});

it('keeps nothing when the source fails outright', function(): void {
    Http::fake(['*' => Http::response('', 500)]);

    expect(downloadPortrait()(COMMONS_THUMB, 'alguien'))->toBeNull();
});

it('keeps nothing when there is no url to fetch', function(): void {
    Http::fake();

    expect(downloadPortrait()(null, 'alguien'))->toBeNull();

    Http::assertNothingSent();
});

it('refuses a host that is not an allowed portrait source', function(): void {
    Http::fake(['*' => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg'])]);

    /* An allowed cover host is not an allowed portrait host: the two lists are
       separate on purpose. */
    foreach ([
        'https://evil.example/portrait.jpg',
        'https://covers.openlibrary.org/portrait.jpg',
        'https://wikimedia.org.evil.example/portrait.jpg',
    ] as $url) {
        expect(downloadPortrait()($url, 'alguien'))->toBeNull("{$url} was not refused");
    }

    Http::assertNothingSent();
});

it('refuses to reach internal addresses', function(): void {
    Http::fake(['*' => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg'])]);

    foreach ([
        'https://127.0.0.1/portrait.jpg',
        'https://169.254.169.254/latest/meta-data/iam/security-credentials/',
        'https://10.0.0.5/portrait.jpg',
        'https://[::1]/portrait.jpg',
        'https://localhost/portrait.jpg',
    ] as $url) {
        expect(downloadPortrait()($url, 'alguien'))->toBeNull("{$url} was not refused");
    }

    Http::assertNothingSent();
});

it('refuses a plain http url', function(): void {
    Http::fake(['*' => Http::response(fakeCover(), 200, ['Content-Type' => 'image/jpeg'])]);

    expect(downloadPortrait()('http://thumb.wikimedia.org/portrait.jpg', 'alguien'))->toBeNull();

    Http::assertNothingSent();
});

it('allows the thumbnail host Commons actually answers with', function(): void {
    /* iiurlwidth hands back a thumb.wikimedia.org URL, not upload.wikimedia.org
       -- the wildcard in the allowed list is what covers it. */
    Http::fake(['*' => Http::response(fakeCover(1200, 1600), 200, ['Content-Type' => 'image/jpeg'])]);

    expect(downloadPortrait()(COMMONS_THUMB, 'sastre-elvira'))->not->toBeNull();
});
