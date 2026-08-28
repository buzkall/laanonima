<?php

namespace App\Actions\Books;

use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

/**
 * Pull a cover onto our own disk rather than hotlinking it.
 *
 * Open Library asks not to be used as a CDN, source records disappear, and
 * DILVE will hand covers over the same way through getResourceX.
 *
 * Whatever a source serves is normalised on the way in: decoded, measured
 * against a floor that rejects placeholders, downscaled, and re-encoded as
 * JPEG.
 */
class DownloadBookCover
{
    /**
     * Every stored cover is re-encoded to JPEG, so callers can look one up on
     * the disk without guessing at the source's format.
     */
    public const EXTENSION = 'jpg';

    /**
     * @return string|null the stored path, relative to the covers disk
     */
    public function __invoke(?string $url, string $isbn13): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (! $this->isAllowed($url)) {
            Log::warning('Cover URL refused: host is not an allowed cover source.', [
                'isbn13' => $isbn13,
                'url'    => $url,
            ]);

            return null;
        }

        try {
            $response = Http::timeout(config('books.metadata.timeout'))
                ->withUserAgent(config('books.metadata.user_agent'))
                ->withOptions([
                    'allow_redirects' => [
                        'max'         => 5,
                        'protocols'   => ['https'],
                        'strict'      => true,
                        'referer'     => false,
                        'on_redirect' => function(mixed $request, mixed $response, UriInterface $uri): void {
                            if (! $this->isAllowed((string)$uri)) {
                                throw new RuntimeException("Cover redirect refused: {$uri}");
                            }
                        },
                    ],
                ])
                ->retry(2, 200, throw: false)
                ->get($url);
        } catch (Throwable $exception) {
            Log::warning('Cover download failed.', ['isbn13' => $isbn13, 'exception' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > (int)config('books.covers.max_bytes')) {
            return null;
        }

        $image = $this->decode($body, $isbn13);

        if ($image === null) {
            return null;
        }

        $path = config('books.covers.directory') . "/{$isbn13}." . self::EXTENSION;

        Storage::disk(config('books.covers.disk'))->put($path, $this->encode($image), 'public');

        return $path;
    }

    /**
     * Is this a URL we are willing to fetch server-side?
     *
     * Cover URLs come out of provider responses rather than our own code, so
     * they get the same treatment as any other untrusted input: https only, and
     * only hosts we chose. The check runs again on every redirect hop, because
     * an allowed host is still free to send us somewhere else.
     */
    private function isAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? null) !== 'https') {
            return false;
        }

        $host = $parts['host'] ?? null;

        if (! is_string($host) || blank($host)) {
            return false;
        }

        return Str::is(config('books.covers.allowed_hosts'), $host);
    }

    /**
     * Decode the payload and reject anything that is not a usable cover.
     *
     * A source that has no cover for an ISBN tends to answer with a placeholder
     * and a 200, so the status code proves nothing and the size floor does the
     * real work here.
     */
    private function decode(string $body, string $isbn13): ?GdImage
    {
        $image = @imagecreatefromstring($body);

        if ($image === false) {
            Log::warning('Cover was not a decodable image.', ['isbn13' => $isbn13]);

            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < (int)config('books.covers.min_width') || $height < (int)config('books.covers.min_height')) {
            Log::warning('Cover rejected as too small to be a real cover.', [
                'isbn13' => $isbn13,
                'width'  => $width,
                'height' => $height,
            ]);

            return null;
        }

        return $image;
    }

    /**
     * Downscale to fit the configured box, then encode as JPEG over white so a
     * transparent PNG or GIF does not come out black.
     */
    private function encode(GdImage $image): string
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $scale = min(
            (int)config('books.covers.max_width') / $width,
            (int)config('books.covers.max_height') / $height,
            1,
        );

        $targetWidth = max(1, (int)round($width * $scale));
        $targetHeight = max(1, (int)round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        /** A truecolor canvas takes the colour as a plain RGB integer. */
        imagefill($canvas, 0, 0, 0xFFFFFF);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, (int)config('books.covers.quality'));

        return (string)ob_get_clean();
    }
}
