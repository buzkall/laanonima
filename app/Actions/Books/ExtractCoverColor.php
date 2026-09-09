<?php

namespace App\Actions\Books;

use App\Actions\Images\ColorOfImage;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * The dominant color of a cover, as a "#rrggbb" string.
 *
 * Reading the pixels is `ColorOfImage`'s job; what belongs here is finding the
 * bytes. Nothing is allowed to break a save, so a missing, unreadable or
 * non-image file yields null rather than an exception.
 */
class ExtractCoverColor
{
    public function __construct(private ColorOfImage $color = new ColorOfImage) {}

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

            $bytes = (string)$disk->get($path);
        } catch (Throwable) {
            return null;
        }

        return ($this->color)($bytes);
    }
}
