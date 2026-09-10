<?php

namespace App\Actions\Images;

use GdImage;

/**
 * The dominant color of some image bytes, as a "#rrggbb" string.
 *
 * The whole image is resampled onto a single pixel and that pixel is read back,
 * which is the same one-pixel average the ecosystem packages and Laravel's own
 * image API arrive at. It costs one GD decode and no new dependency.
 *
 * The result is a tint to sit behind an image while it loads, or to color the
 * surrounding card -- not a palette. Bytes that GD cannot read yield null rather
 * than an exception, so nothing here is allowed to break a save.
 *
 * Taking bytes rather than a stored file is what lets a portrait be normalized
 * in memory and its color read before anything is written anywhere;
 * `ExtractCoverColor` reads a cover off its disk and hands the bytes here.
 */
class ColorOfImage
{
    public function __invoke(string $bytes): ?string
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        return $this->averageColor($image);
    }

    /**
     * Over white, the same ground the images are encoded against, so a
     * transparent source averages towards white instead of black.
     */
    private function averageColor(GdImage $image): string
    {
        $pixel = imagecreatetruecolor(1, 1);

        /** A truecolor canvas takes the color as a plain RGB integer. */
        imagefill($pixel, 0, 0, 0xFFFFFF);
        imagecopyresampled($pixel, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));

        $rgb = imagecolorat($pixel, 0, 0);

        return sprintf('#%02x%02x%02x', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }
}
