<?php

namespace App\Support\Og;

use App\Actions\Images\ComposeOgCard;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Keeps one drawn card per record on the disk.
 *
 * The card is built the first time somebody opens the page it belongs to, and
 * from then on it is a file the web server hands over through the storage
 * symlink with no PHP in the way. A page render costs one stat() once the card
 * exists.
 *
 * Drawing must never take a page down with it: a font that did not deploy or a
 * cover GD chokes on leaves the page sharing without a picture, which is where
 * it started, rather than throwing a 500 at a reader.
 */
final readonly class OgCardStore
{
    public function __construct(private ComposeOgCard $compose = new ComposeOgCard) {}

    /**
     * The absolute URL of the card for this record, drawing and filing it if
     * this is the first time it has been asked for. Null when it could not be
     * drawn at all, so the caller can leave the tag off.
     *
     * @param  callable():OgCard  $card  deferred, because building one reads
     *                                   every cover off the disk and a card
     *                                   that already exists needs none of them
     */
    public function url(OgCardKey $key, callable $card): ?string
    {
        $disk = Storage::disk((string)config('og.disk'));

        try {
            if ($disk->exists($key->path())) {
                return $key->url();
            }

            $disk->put($key->path(), ($this->compose)($card()));

            $this->sweep($key);
        } catch (Throwable) {
            return null;
        }

        return $key->url();
    }

    /**
     * Drop the cards this record used to have. The fingerprint means a retitled
     * book writes a new file rather than overwriting the old one, so without
     * this the directory would grow by one card per edit forever.
     */
    private function sweep(OgCardKey $key): void
    {
        $disk = Storage::disk((string)config('og.disk'));
        $keep = $key->path();

        foreach ($disk->files($key->folder()) as $file) {
            if ($file !== $keep && str_starts_with($file, $key->pattern())) {
                $disk->delete($file);
            }
        }
    }
}
