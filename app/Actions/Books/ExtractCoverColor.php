<?php

namespace App\Actions\Books;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * The dominant colour of a cover, as a "#rrggbb" string.
 *
 * The cover is resampled down to a single pixel and that pixel is read back,
 * which is the same one-pixel average the ecosystem packages and Laravel's own
 * image API arrive at. It costs one GD decode and no new dependency: the covers
 * pipeline already speaks GD.
 *
 * The result is a tint to sit behind a cover while it loads, or to colour the
 * card around it — not a palette. Nothing here is allowed to break a save, so
 * an unreadable or missing file yields null rather than an exception.
 */
class ExtractCoverColor
{
    /**
     * Read through the disk rather than Media::getPath(), which resolves to a
     * local filesystem path and would stop working the day covers move to S3.
     */
    public function __invoke(?Media $cover): ?string
    {
        if (! $cover instanceof Media) {
            return null;
        }

        try {
            $disk = Storage::disk($cover->disk);
            $path = $cover->getPathRelativeToRoot();

            if (! $disk->exists($path)) {
                return null;
            }

            $image = @imagecreatefromstring((string)$disk->get($path));
        } catch (Throwable) {
            return null;
        }

        if ($image === false) {
            return null;
        }

        return $this->averageColor($image);
    }

    /**
     * Resample the whole cover onto one pixel, over the same white ground the
     * covers pipeline encodes against, so a transparent PNG averages towards
     * white instead of black.
     */
    private function averageColor(GdImage $image): string
    {
        $pixel = imagecreatetruecolor(1, 1);

        /** A truecolor canvas takes the colour as a plain RGB integer. */
        imagefill($pixel, 0, 0, 0xFFFFFF);
        imagecopyresampled($pixel, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));

        $rgb = imagecolorat($pixel, 0, 0);

        return sprintf('#%02x%02x%02x', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }
}
