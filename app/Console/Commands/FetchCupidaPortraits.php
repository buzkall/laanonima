<?php

namespace App\Console\Commands;

use App\Actions\Images\ColorOfImage;
use App\Actions\Portraits\AttachAuthorPortrait;
use App\Actions\Portraits\DownloadPortrait;
use App\Support\Cupida\CupidaCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Puts the author portraits on this machine's disk.
 *
 * The metadata is committed and the images are not, so this is a deploy step:
 * it reads the image_url `cupida:portraits:resolve` recorded and downloads what
 * is missing. No Wikidata traffic, no writes back to the JSON, nothing to
 * review -- it is the boring half on purpose, because it runs unattended.
 *
 * It exits 0 even when downloads fail. A deploy must not be blocked because
 * Commons was slow, and a portrait that did not arrive is already a faceless
 * card rather than a broken image: `CupidaCatalog::portrait()` checks the
 * disk, not just the metadata.
 *
 * Every portrait on the disk is also filed on the shop's own author record
 * where there is one, so the public author page shows the same face. That
 * pass runs over what was already on disk as well as what just arrived: a
 * writer the shop files after the deploy gets their portrait on the next run
 * rather than never.
 */
#[Description('Download La Cupida author portraits onto this machine')]
#[Signature('cupida:portraits:fetch
        {--force : Download again even for portraits already on disk}
        {--limit= : Download at most this many this run}')]
class FetchCupidaPortraits extends Command
{
    public function handle(
        CupidaCatalog $catalog,
        DownloadPortrait $download,
        AttachAuthorPortrait $attach,
        ColorOfImage $color,
    ): int {
        $disk = Storage::disk(config('cupida.portraits.disk'));

        $matched = array_filter(
            $catalog->portraits(),
            fn(array $entry): bool => ($entry['status'] ?? null) === 'matched'
                && filled($entry['photo'] ?? null)
                && filled($entry['image_url'] ?? null),
        );

        if ($matched === []) {
            $this->components->info('No portraits to fetch. Run cupida:portraits:resolve first.');

            return self::SUCCESS;
        }

        $wanted = $this->option('force')
            ? $matched
            : array_filter($matched, fn(array $entry): bool => ! $disk->exists((string)$entry['photo']));

        $limit = $this->option('limit');

        if (is_numeric($limit)) {
            $wanted = array_slice($wanted, 0, (int)$limit, preserve_keys: true);
        }

        $had = count($matched) - count($wanted);
        $downloaded = 0;
        $failed = 0;
        $linked = 0;

        foreach ($wanted as $slug => $entry) {
            $bytes = $download((string)$entry['image_url'], (string)$slug);

            if ($bytes === null) {
                $failed++;
                $this->components->twoColumnDetail((string)$entry['name'], '<fg=yellow>failed</>');

                continue;
            }

            $disk->put((string)$entry['photo'], $bytes);
            $downloaded++;

            /* A fresh download replaces whatever the author page had: the
               metadata may have been re-resolved to a better photo. */
            $linked += $attach($entry, $bytes, replace: true) ? 1 : 0;

            $this->components->twoColumnDetail((string)$entry['name'], '<fg=green>fetched</>');
        }

        /* What was already on disk still needs filing on an author whose
           record has no face yet -- the shop adds writers after the deploy. */
        foreach (array_diff_key($matched, $wanted) as $entry) {
            $bytes = $disk->get((string)$entry['photo']);

            if ($bytes === null) {
                continue;
            }

            $linked += $attach($entry, $bytes) ? 1 : 0;
        }

        $this->newLine();
        $this->components->info("Downloaded {$downloaded}, already had {$had}, {$failed} failed, {$linked} filed on author pages.");

        return self::SUCCESS;
    }
}
