<?php

namespace App\Actions\Books;

use App\Actions\Images\ColorOfImage;
use App\Actions\Images\MediaBytes;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The dominant color of a cover, as a "#rrggbb" string.
 *
 * Reading the pixels is `ColorOfImage`'s job and finding the bytes is
 * `MediaBytes`'; what is left here is the pair of them. Nothing is allowed to
 * break a save, so a missing, unreadable or non-image file yields null rather
 * than an exception -- both collaborators already hold to that.
 */
class ExtractCoverColor
{
    public function __construct(
        private ColorOfImage $color = new ColorOfImage,
        private MediaBytes $bytes = new MediaBytes,
    ) {}

    public function __invoke(?Media $cover): ?string
    {
        $bytes = ($this->bytes)($cover);

        return $bytes === null ? null : ($this->color)($bytes);
    }
}
