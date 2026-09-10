<?php

namespace App\Actions\Images;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * The bytes behind a media record, or null if they cannot be had.
 *
 * Read through the disk rather than Media::getPath(), which resolves to a local
 * filesystem path and would stop working the day covers move to S3. That rule
 * used to live inside ExtractCoverColor; it lives here now because the share
 * cards need the same bytes and a rule kept in two places is a rule that will
 * be followed in one.
 *
 * A missing file, an unreadable disk or a record whose conversion was never
 * generated all yield null rather than an exception: nothing that reads an
 * image is allowed to break the page or the save that asked for it.
 */
class MediaBytes
{
    /**
     * @param  string  $conversion  the conversion to prefer; falls back to the
     *                              original when it was never generated
     */
    public function __invoke(?Media $media, string $conversion = ''): ?string
    {
        if (! $media instanceof Media) {
            return null;
        }

        try {
            $disk = Storage::disk($media->disk);
            $path = $conversion !== '' && $media->hasGeneratedConversion($conversion)
                ? $media->getPathRelativeToRoot($conversion)
                : $media->getPathRelativeToRoot();

            if (! $disk->exists($path)) {
                return null;
            }

            return (string)$disk->get($path);
        } catch (Throwable) {
            return null;
        }
    }
}
