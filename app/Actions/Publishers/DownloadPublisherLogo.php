<?php

namespace App\Actions\Publishers;

use App\Enums\LogoOrigin;
use App\Support\PublisherLogos\PublicUrl;
use App\Support\RemoteImage;
use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pull a publisher's logotype onto our own disk as a PNG that keeps its alpha.
 *
 * Not DownloadBookCover or DownloadPortrait with other numbers: both flatten
 * onto white and re-encode as JPEG, which puts a white box behind a logotype on
 * every colored background it is shown on, and both reject anything shorter
 * than a book, which is every wordmark there is. Here the image is only ever
 * scaled down, never cropped, and always written back as PNG.
 *
 * Filing it is somebody else's job: this hands the bytes back.
 */
class DownloadPublisherLogo
{
    /**
     * Every logotype is re-encoded to PNG, so callers can name the file
     * without guessing at the source's format.
     */
    public const string EXTENSION = 'png';

    public function __construct(private readonly PublicUrl $publicUrl) {}

    /**
     * @param  string  $slug  only ever used to say which publisher a warning is about
     * @return string|null PNG bytes, no larger than `max_width` x `max_height`
     */
    public function __invoke(?string $url, LogoOrigin $origin, string $slug): ?string
    {
        if (blank($url)) {
            return null;
        }

        $options = $this->options($url, $origin);

        if ($options === null) {
            Log::warning('Publisher logo URL refused.', ['slug' => $slug, 'url' => $url, 'origin' => $origin->value]);

            return null;
        }

        try {
            $response = Http::withUserAgent((string)config('publishers.logos.user_agent'))
                ->timeout((int)config($origin === LogoOrigin::Website ? 'publishers.logos.website.timeout' : 'publishers.logos.timeout'))
                ->withOptions($options)
                ->get($url);
        } catch (Throwable $exception) {
            Log::warning('Publisher logo download failed.', ['slug' => $slug, 'exception' => $exception->getMessage()]);

            return null;
        }

        $body = $response->body();

        if (! $response->successful() || $body === '' || strlen($body) > (int)config('publishers.logos.max_bytes')) {
            return null;
        }

        $image = $this->decode($body, $slug);

        return $image instanceof GdImage ? $this->normalize($image) : null;
    }

    /**
     * A Commons thumbnail comes from hosts we chose; a website's icon from
     * whatever public host the site is on.
     *
     * @return array<string, mixed>|null
     */
    private function options(string $url, LogoOrigin $origin): ?array
    {
        $hosts = (array)config('publishers.logos.allowed_hosts');

        return match ($origin) {
            LogoOrigin::Wikidata => RemoteImage::allowed($url, $hosts) ? RemoteImage::redirectGuard($hosts) : null,
            LogoOrigin::Website  => $this->publicUrl->options($url),
        };
    }

    /**
     * Decode the payload and reject what is not a usable raster logotype.
     *
     * SVG and ICO are refused by their first bytes before GD sees them: neither
     * decodes here, and a site answering a missing icon with its HTML home page
     * and a 200 is common enough that the status proves nothing.
     */
    private function decode(string $body, string $slug): ?GdImage
    {
        if (str_starts_with($body, "\x00\x00\x01\x00") || preg_match('/^\s*(<\?xml|<svg|<!doctype|<html)/i', substr($body, 0, 256)) === 1) {
            Log::warning('Publisher logo was not a raster image.', ['slug' => $slug]);

            return null;
        }

        $image = @imagecreatefromstring($body);

        if ($image === false) {
            Log::warning('Publisher logo was not a decodable image.', ['slug' => $slug]);

            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) < (int)config('publishers.logos.min_long_side') || min($width, $height) < (int)config('publishers.logos.min_short_side')) {
            Log::warning('Publisher logo rejected as too small.', ['slug' => $slug, 'width' => $width, 'height' => $height]);

            return null;
        }

        return $image;
    }

    /**
     * Scale down to fit, never up, onto a fully transparent canvas.
     */
    private function normalize(GdImage $image): string
    {
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $scale = min(1, (int)config('publishers.logos.max_width') / $width, (int)config('publishers.logos.max_height') / $height);

        $targetWidth = max(1, (int)round($width * $scale));
        $targetHeight = max(1, (int)round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, (int)imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagepng($canvas, null, 9);

        return (string)ob_get_clean();
    }
}
