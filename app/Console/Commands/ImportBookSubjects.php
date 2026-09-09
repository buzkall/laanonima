<?php

namespace App\Console\Commands;

use App\Support\Shop\ShopScraper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fetches the subject tree the catalog is classified with.
 *
 * The shop files every book under THEMA, the international subject scheme, and
 * publishes the whole tree in Spanish on its own site -- which is the version we
 * want rather than EDItEUR's, because a bookseller should meet the same wording
 * in the panel as on the shelf.
 *
 * Written to `database/seeders/data/subjects.json` and committed, so seeding
 * needs no network. Run by hand, rarely: THEMA revises about once a year.
 *
 * Walked breadth first, one request per node that has children. Codes nest by
 * prefix, so a node's children are the codes that start with it -- that is what
 * `ShopScraper::childrenOf()` filters on, and it is also why the resulting table
 * needs no recursive query later.
 */
#[Description('Fetch the THEMA subject tree from the shop and write it for the seeder')]
#[Signature('books:import-subjects
        {--delay= : Milliseconds between requests, defaults to cupida.scrape.delay_ms}
        {--depth=4 : How many levels below the top to walk}
        {--fresh : Ignore what is already on disk and start the tree over}')]
class ImportBookSubjects extends Command
{
    /** @var array<string, array<string, mixed>> keyed by code */
    private array $subjects = [];

    private int $delayMicroseconds = 0;

    public function handle(ShopScraper $shop): int
    {
        $this->delayMicroseconds = (int)($this->option('delay') ?? config('cupida.scrape.delay_ms')) * 1000;

        $this->resume();

        try {
            $this->walk($shop);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            if ($this->subjects === []) {
                return self::FAILURE;
            }

            $this->components->warn('Writing what was read before the failure.');
        }

        $this->write();

        return self::SUCCESS;
    }

    /**
     * Pick up a tree a previous run left half-read.
     *
     * Six hundred requests against a small bookseller is not something to repeat
     * because the connection dropped at code R.
     */
    private function resume(): void
    {
        $path = $this->path();

        if ($this->option('fresh') || ! is_file($path)) {
            return;
        }

        $decoded = json_decode((string)file_get_contents($path), associative: true);

        foreach (is_array($decoded) ? $decoded : [] as $subject) {
            $this->subjects[(string)$subject['code']] = $subject;
        }

        if ($this->subjects !== []) {
            $this->components->info(sprintf(
                'Resuming on %d subjects. Pass --fresh to start over.',
                count($this->subjects),
            ));
        }
    }

    private function walk(ShopScraper $shop): void
    {
        if ($this->subjects === []) {
            $this->components->info('Reading the subject index.');
            $this->record($shop->subjects(), parent: null);
            $this->pause();
        }

        $depth = (int)$this->option('depth');

        for ($level = 0; $level < $depth; $level++) {
            $frontier = $this->unopened();

            if ($frontier === []) {
                $this->components->info('The whole tree has been read.');

                break;
            }

            $this->components->info(sprintf('Level %d: %d subjects to open.', $level + 1, count($frontier)));

            $bar = $this->output->createProgressBar(count($frontier));
            $bar->start();

            foreach ($frontier as $code) {
                try {
                    $this->record($shop->childrenOf($code, (string)$this->subjects[$code]['slug']), parent: $code);

                    /* Asked, whether or not it turned out to have children. A
                       leaf that is not marked is a leaf the next run asks about
                       again, which on a finished tree is a thousand pointless
                       requests. */
                    $this->subjects[$code]['opened'] = true;
                } catch (Throwable $exception) {
                    $this->newLine();
                    $this->components->warn("{$code}: {$exception->getMessage()}");
                }

                $this->pause();
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            /* Written after every level, not once at the end. A walk of the
               whole tree is the best part of an hour, and a resume that has
               nothing on disk to resume from is not a resume. */
            $this->write();
        }
    }

    /**
     * The codes we have not yet asked the shop about.
     *
     * A qualifier axis -- THEMA's `1` place, `5` interest, `6` style and the
     * rest -- is a different kind of thing from a subject: it says who a book is
     * for or where it is set, not what it is about. Six of them appear across
     * five thousand books. They are not what `books.subject_id` means, so the
     * walk never descends into them.
     *
     * @return array<int, string>
     */
    private function unopened(): array
    {
        return array_values(array_filter(
            array_keys($this->subjects),
            fn(string $code): bool => ($this->subjects[$code]['opened'] ?? false) !== true
                && ! ctype_digit($code[0] ?? ''),
        ));
    }

    /**
     * @param  array<string, string>  $found  code => heading
     * @return array<int, string> the codes that were new
     */
    private function record(array $found, ?string $parent): array
    {
        $new = [];

        foreach ($found as $code => $heading) {
            $code = (string)$code;

            if (isset($this->subjects[$code])) {
                continue;
            }

            $this->subjects[$code] = [
                'code'   => $code,
                'name'   => $heading,
                'parent' => $parent,
                'slug'   => Str::slug("{$code} {$heading}"),
                'opened' => false,
            ];

            $new[] = $code;
        }

        return $new;
    }

    private function write(): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, recursive: true);
        }

        $subjects = array_values($this->subjects);

        /* Sorted by code so the committed file has a stable order and a re-run
           produces a readable diff rather than a reshuffle. */
        usort($subjects, fn(array $a, array $b): int => strcmp((string)$a['code'], (string)$b['code']));

        $temporary = $path . '.writing';

        file_put_contents(
            $temporary,
            json_encode($subjects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        rename($temporary, $path);

        $this->components->twoColumnDetail(
            Str::after($path, base_path() . '/'),
            sprintf('%d subjects', count($subjects)),
        );
    }

    private function path(): string
    {
        return database_path('seeders/data/subjects.json');
    }

    private function pause(): void
    {
        if ($this->delayMicroseconds > 0 && ! app()->runningUnitTests()) {
            usleep($this->delayMicroseconds);
        }
    }
}
