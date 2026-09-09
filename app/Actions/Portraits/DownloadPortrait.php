<?php

namespace App\Actions\Portraits;

use App\Support\RemoteImage;
use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pull an author's photo onto our own disk and crop it to the card.
 *
 * Wikimedia asks not to be hotlinked, and the card wants one shape from sources
 * that have every shape there is -- a 1902 studio plate, a convention snapshot,
 * a full-length portrait. So whatever Commons serves is decoded, measured
 * against a floor that rejects placeholders, cropped to fill the card's box and
 * re-encoded as JPEG. Filing it is somebody else's job: this hands the bytes
 * back.
 *
 * The same shape as DownloadBookCover, and it shares that action's SSRF guard,
 * because the URL comes out of an API response rather than out of our code.
 */
class DownloadPortrait
{
    /**
     * Every portrait is re-encoded to JPEG, so callers can name the file
     * without guessing at the source's format.
     */
    public const EXTENSION = 'jpg';

    /**
     * @param  string  $slug  only ever used to say which author a warning is about
     * @return string|null the normalized JPEG bytes, cropped to the card's box
     */
    public function __invoke(?string $url, string $slug): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (! RemoteImage::allowed($url, $this->hosts())) {
            Log::warning('Portrait URL refused: host is not an allowed portrait source.', [
                'slug' => $slug,
                'url'  => $url,
            ]);

            return null;
        }

        try {
            $response = Http::timeout(config('cupida.portraits.timeout'))
                ->withUserAgent(config('cupida.portraits.user_agent'))
                ->withOptions(RemoteImage::redirectGuard($this->hosts()))
                ->retry(2, 200, throw: false)
                ->get($url);
        } catch (Throwable $exception) {
            Log::warning('Portrait download failed.', ['slug' => $slug, 'exception' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > (int)config('cupida.portraits.max_bytes')) {
            return null;
        }

        $image = $this->decode($body, $slug);

        if (! $image instanceof GdImage) {
            return null;
        }

        return $this->crop($image);
    }

    /**
     * @return array<int, string>
     */
    private function hosts(): array
    {
        return (array)config('cupida.portraits.allowed_hosts');
    }

    /**
     * Decode the payload and reject anything too small to hold a face.
     *
     * A source with nothing for a name tends to answer with a placeholder and a
     * 200, so the status code proves nothing and the floor does the real work.
     */
    private function decode(string $body, string $slug): ?GdImage
    {
        $image = @imagecreatefromstring($body);

        if ($image === false) {
            Log::warning('Portrait was not a decodable image.', ['slug' => $slug]);

            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < (int)config('cupida.portraits.min_width') || $height < (int)config('cupida.portraits.min_height')) {
            Log::warning('Portrait rejected as too small to hold a face.', [
                'slug'   => $slug,
                'width'  => $width,
                'height' => $height,
            ]);

            return null;
        }

        return $image;
    }

    /**
     * Fill the card's box rather than fit inside it, and take the crop from
     * near the top.
     *
     * `max` and not `min` is the whole difference from DownloadBookCover: a
     * cover is fitted into its box and may letterbox, a portrait has to cover
     * its frame or the card shows a stripe of background down one side. Every
     * portrait therefore comes out at exactly the configured size.
     *
     * The vertical anchor is `crop_anchor` rather than the middle because a
     * Commons portrait is usually head-and-shoulders in the upper half of the
     * frame, and a centered crop takes the top of the head off a full-length
     * photograph.
     */
    private function crop(GdImage $image): string
    {
        $width = imagesx($image);
        $height = imagesy($image);

        /* max(1) because these come out of config: a zero would be a fatal
           inside GD rather than a small picture. */
        $targetWidth = max(1, (int)config('cupida.portraits.width'));
        $targetHeight = max(1, (int)config('cupida.portraits.height'));

        $scale = max($targetWidth / $width, $targetHeight / $height);

        $sourceWidth = min($width, max(1, (int)round($targetWidth / $scale)));
        $sourceHeight = min($height, max(1, (int)round($targetHeight / $scale)));

        $sourceX = (int)round(($width - $sourceWidth) / 2);
        $sourceY = (int)round(($height - $sourceHeight) * (float)config('cupida.portraits.crop_anchor'));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        /** A truecolor canvas takes the color as a plain RGB integer. */
        imagefill($canvas, 0, 0, 0xFFFFFF);
        imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            $sourceX,
            $sourceY,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        ob_start();
        imagejpeg($canvas, null, (int)config('cupida.portraits.quality'));

        return (string)ob_get_clean();
    }
}
