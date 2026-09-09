<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Holding and writing author-photos.json.
 *
 * Shared by the two portrait commands because they both read the file and one
 * of them writes it back, and the atomic-write dance is the sort of thing that
 * gets copied slightly wrong.
 *
 * @phpstan-require-extends Command
 */
trait WritesCupidaPortraits
{
    /** @var array<string, array<string, mixed>> keyed by author slug */
    private array $portraits = [];

    private function portraitsPath(): string
    {
        return rtrim((string)config('cupida.data_path'), '/') . '/author-photos.json';
    }

    /**
     * Write beside the file and move it into place.
     *
     * A run takes minutes and the site is up throughout it, so a reader can ask
     * for a recommendation in the middle of one. `file_put_contents` truncates
     * first and fills after, which leaves a window where the file is half a
     * JSON document; a rename on the same filesystem has no such window.
     *
     * Sorted, so a run that added four names is four hunks in the diff rather
     * than a reshuffle of the whole file.
     */
    private function write(): void
    {
        $path = $this->portraitsPath();

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, recursive: true);
        }

        ksort($this->portraits);

        $temporary = $path . '.writing';

        file_put_contents(
            $temporary,
            json_encode(
                $this->portraits,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );

        rename($temporary, $path);

        $this->components->twoColumnDetail(
            Str::after($path, base_path() . '/'),
            (string)count($this->portraits),
        );
    }
}
