<?php

namespace App\Console\Commands;

use App\Actions\Images\ColorOfImage;
use App\Actions\Portraits\DownloadPortrait;
use App\Support\Cupida\CupidaCatalog;
use App\Support\Portraits\PortraitMatch;
use App\Support\Portraits\PortraitSource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Works out which Wikidata item each of the deck's authors is, and where their
 * photo lives.
 *
 * Run by hand and the result committed, like `cupida:scrape` -- but into a
 * fourth file, resources/data/cupida/author-photos.json, and that is not a
 * stylistic choice. `ScrapeCupidaCatalog::authors()` rebuilds authors.json
 * from the books pool on every run, so a photo recorded in there would survive
 * exactly until the next scrape and then disappear with no error at all.
 *
 * Only the metadata is written here. The JPEGs are downloaded on deploy by
 * `cupida:portraits:fetch`, off the image_url this command records.
 *
 * What it cannot do is tell a good photograph from a bad one, or notice that a
 * correctly identified writer's only picture is a statue. That is what --sheet
 * is for: it renders every face it found onto one page, and the two pins
 * (--qid, --file) are how a person corrects what they see there.
 */
#[Description('Find a face for the authors La Cupida deals cards from')]
#[Signature('cupida:portraits:resolve
        {--limit= : Look up at most this many authors this run}
        {--only= : Only these author slugs, comma separated}
        {--qid= : Pin this Wikidata item; only with a single --only}
        {--file= : Pin this Commons file; only with a single --only}
        {--none : Record that this author is not a person; only with --only}
        {--reject : Record that the photo found for this author is not usable; only with --only}
        {--retry-misses : Ask again about the authors an earlier run could not match}
        {--fresh : Look every unpinned author up again from scratch}
        {--forget-pins : With --fresh, throw the hand-corrected entries away too}
        {--delay= : Milliseconds between requests, defaults to cupida.portraits.delay_ms}
        {--sheet : Write the contact sheet for review and make no requests at all}')]
class ResolveCupidaPortraits extends Command
{
    use WritesCupidaPortraits;

    private int $delayMicroseconds = 0;

    public function handle(
        CupidaCatalog $catalog,
        PortraitSource $source,
        DownloadPortrait $download,
        ColorOfImage $color,
    ): int {
        if (! $this->guardPins()) {
            return self::FAILURE;
        }

        $this->delayMicroseconds = (int)($this->option('delay') ?? config('cupida.portraits.delay_ms')) * 1000;

        $this->resume();

        if ($this->option('sheet')) {
            return $this->sheet();
        }

        $outstanding = $this->thisRun($this->outstanding($catalog));

        try {
            foreach ($outstanding as $index => $author) {
                $this->look($author, $source, $download, $color);

                if ($index < count($outstanding) - 1) {
                    $this->pause();
                }
            }
        } catch (Throwable $exception) {
            /* Write what was collected before failing. Losing forty good
               lookups to one 500 on the forty-first is what makes a hand-run
               command annoying enough never to be run twice. */
            $this->components->error("Stopped early: {$exception->getMessage()}");
            $this->write();

            return self::FAILURE;
        }

        $this->write();
        $this->report($catalog);

        return self::SUCCESS;
    }

    /**
     * A pin applies to one author, or it is a mistake with no undo.
     */
    private function guardPins(): bool
    {
        /* `--none` is a flag and the other two are values, so they cannot be
           tested the same way: `filled(false)` is true, which made every run
           look like a pinned one. */
        $pinning = $this->option('none') === true
            || $this->option('reject') === true
            || filled($this->option('qid'))
            || filled($this->option('file'));

        if (! $pinning) {
            return true;
        }

        if (count($this->slugsAsked()) !== 1) {
            $this->components->error('--qid, --file and --none need exactly one --only slug.');

            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function slugsAsked(): array
    {
        $only = $this->option('only');

        if (! is_string($only) || blank($only)) {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $only))));
    }

    /**
     * Read what earlier runs decided, and keep the hand corrections whatever
     * else happens.
     *
     * `--fresh` here does *not* mean what it means in `cupida:scrape`. There it
     * drops everything; here it keeps the pinned rows, because those are the
     * output of somebody looking at a hundred and eighteen faces one at a time,
     * and a flag that silently destroys an afternoon is a flag nobody runs
     * twice. `--forget-pins` is the way to mean it.
     */
    private function resume(): void
    {
        $this->portraits = app(CupidaCatalog::class)->portraits();

        if (! $this->option('fresh')) {
            return;
        }

        if ($this->option('forget-pins')) {
            $this->components->warn('Starting over, hand-corrected entries included.');
            $this->portraits = [];

            return;
        }

        $pinned = array_filter($this->portraits, fn(array $entry): bool => ($entry['pinned'] ?? false) === true);
        $this->portraits = $pinned;

        $this->components->warn(sprintf(
            'Starting over. %d pinned %s kept; pass --forget-pins to drop those too.',
            count($pinned),
            Str::plural('entry', count($pinned)),
        ));
    }

    /**
     * The authors this run should ask about.
     *
     * @return array<int, array<string, mixed>>
     */
    private function outstanding(CupidaCatalog $catalog): array
    {
        $pool = array_slice($catalog->authors(), 0, (int)config('cupida.portraits.pool'));
        $asked = $this->slugsAsked();

        if ($asked !== []) {
            $known = array_column($pool, 'slug');

            foreach (array_diff($asked, $known) as $stranger) {
                $this->components->warn("{$stranger} is not in the portraits pool; asking anyway.");
            }

            $wanted = array_values(array_filter($pool, fn(array $a): bool => in_array($a['slug'], $asked, true)));

            /* An --only slug outside the pool is the ordinary case rather than
               the odd one: the deck deals every writer above
               `cupida.deck.author_min_books` and this command walks the top
               `cupida.portraits.pool` of them, which is several times fewer. So
               the name is looked up in the whole catalog, and only a slug that
               is in no file at all falls back to standing for itself -- a row
               named "shine" rather than "Shine" is what that fallback writes. */
            $everyone = array_column($catalog->authors(), null, 'slug');

            foreach (array_diff($asked, $known) as $stranger) {
                $wanted[] = $everyone[$stranger] ?? ['slug' => $stranger, 'name' => $stranger];
            }

            return $wanted;
        }

        return array_values(array_filter($pool, function(array $author): bool {
            $entry = $this->portraits[(string)$author['slug']] ?? null;

            if ($entry === null) {
                return true;
            }

            /* A hand correction is never asked about again, by anything but
               --only. */
            if (($entry['pinned'] ?? false) === true) {
                return false;
            }

            return (bool)$this->option('retry-misses')
                && in_array($entry['status'] ?? '', ['no_match', 'no_image'], true);
        }));
    }

    /**
     * A bite of the remainder rather than all of it.
     *
     * Separate from `outstanding()` so the closing line can report what is
     * genuinely left rather than re-applying the limit to its own answer and
     * reporting the size of the next chunk.
     *
     * @param  array<int, array<string, mixed>>  $outstanding
     * @return array<int, array<string, mixed>>
     */
    private function thisRun(array $outstanding): array
    {
        $limit = $this->option('limit');

        return is_numeric($limit) ? array_slice($outstanding, 0, (int)$limit) : $outstanding;
    }

    /**
     * One author: decide what they are and write the verdict down.
     *
     * @param  array<string, mixed>  $author
     */
    private function look(
        array $author,
        PortraitSource $source,
        DownloadPortrait $download,
        ColorOfImage $color,
    ): void {
        $slug = (string)$author['slug'];
        $name = (string)($author['name'] ?? $slug);

        if ($this->option('none')) {
            $this->record($slug, $name, 'no_person', pinned: true);
            $this->components->twoColumnDetail($name, '<fg=gray>not a person</>');

            return;
        }

        /* A real writer whose only picture on Commons is a statue, a book
           cover, a group shot or -- the case that prompted this -- their own
           signature logo. The occupation guard cannot see any of that, and
           `--none` would be a lie that also drops them from the deck. They keep
           their card and lose the face. */
        if ($this->option('reject')) {
            $this->reject($slug, $name);
            $this->components->twoColumnDetail($name, '<fg=gray>photo not usable</>');

            return;
        }

        /* The shop files anthologies under an author name, and the spelling
           changes every time the catalog grows. Caught here, before any
           request, because there is nothing to ask about. */
        if (CupidaCatalog::isCollectiveName($name)) {
            $this->record($slug, $name, 'no_person', pinned: false);
            $this->components->twoColumnDetail($name, '<fg=gray>not a person</>');

            return;
        }

        $qid = $this->option('qid');
        $file = $this->option('file');

        $match = $source->find(
            $name,
            is_string($qid) && filled($qid) ? $qid : null,
            is_string($file) && filled($file) ? $file : null,
        );

        /* What a person decided by hand, which is what a pin means -- and only
           once the lookup has actually landed on it. A pin taken from the flags
           alone survives the request failing, and `outstanding()` skips a
           pinned row forever: a Wikidata hiccup during `--only=x --qid=Q1`
           would be frozen as a verdict, out of reach of the next run and of
           `--retry-misses` both, and indistinguishable from a miss somebody
           confirmed. */
        $askedByHand = filled($qid) || filled($file);

        if (! $match instanceof PortraitMatch) {
            $this->record($slug, $name, 'no_match', pinned: false);
            $this->components->twoColumnDetail($name, '<fg=yellow>no match</>');

            return;
        }

        if (! $match->hasImage()) {
            $this->record($slug, $name, 'no_image', $askedByHand, $match);
            $this->components->twoColumnDetail($name, "<fg=yellow>{$match->qid}, no photo</>");

            return;
        }

        /* Fetch it once here as well, for two reasons that are not the deploy:
           the color is metadata and has to be committed alongside the credit,
           and it can only be read off the bytes; and --sheet cannot show a face
           that is not on this machine. `cupida:portraits:fetch` is still what
           puts it on a server -- this just means the person doing the review
           already has it. */
        $bytes = $download($match->imageUrl, $slug);

        if ($bytes === null) {
            /* The item is right and the fetch was not -- Commons being slow is
               not a decision, so this one is not pinned either. */
            $this->record($slug, $name, 'no_image', false, $match);
            $this->components->twoColumnDetail($name, '<fg=yellow>photo would not download</>');

            return;
        }

        Storage::disk(config('cupida.portraits.disk'))->put("{$slug}." . DownloadPortrait::EXTENSION, $bytes);

        $this->record($slug, $name, 'matched', $askedByHand, $match, $color($bytes));
        $this->components->twoColumnDetail($name, "<fg=green>{$match->qid}</>");
    }

    /**
     * Keep the author, drop the photo, and remember which item was wrong so
     * nothing ever picks it again.
     */
    private function reject(string $slug, string $name): void
    {
        $existing = $this->portraits[$slug] ?? [];

        $this->portraits[$slug] = [
            'slug'         => $slug,
            'name'         => $name,
            'status'       => 'rejected',
            'qid'          => null,
            'rejected_qid' => $existing['qid'] ?? null,
            'pinned'       => true,
            'wikidata'     => $existing['wikidata'] ?? null,
            'photo'        => null,
            'image_url'    => null,
            'color'        => null,
            'credit'       => null,
            'checked_at'   => Carbon::now()->toDateString(),
        ];

        Storage::disk(config('cupida.portraits.disk'))->delete("{$slug}." . DownloadPortrait::EXTENSION);
    }

    private function record(
        string $slug,
        string $name,
        string $status,
        bool $pinned,
        ?PortraitMatch $match = null,
        ?string $color = null,
    ): void {
        $existing = $this->portraits[$slug] ?? [];

        $this->portraits[$slug] = [
            'slug'     => $slug,
            'name'     => $name,
            'status'   => $status,
            'qid'      => $match?->qid,
            'pinned'   => $pinned || (($existing['pinned'] ?? false) === true && $status === 'no_person'),
            'wikidata' => $match?->qid === null ? null : [
                'label'       => $match->label,
                'description' => $match->description,
                'occupations' => $match->occupations,
            ],
            'photo'     => $status === 'matched' ? "{$slug}." . DownloadPortrait::EXTENSION : null,
            'image_url' => $status === 'matched' ? $match?->imageUrl : null,
            /* Read off the bytes, and committed: a machine with the metadata
               and no images still knows what color to paint behind them. */
            'color'  => $status === 'matched' ? ($color ?? ($existing['color'] ?? null)) : null,
            'credit' => $status === 'matched' && $match instanceof PortraitMatch ? [
                'file'                 => $match->file,
                'artist'               => $match->artist,
                'license'              => $match->license,
                'license_url'          => $match->licenseUrl,
                'attribution_required' => $match->attributionRequired,
                'source_url'           => $match->sourceUrl,
            ] : null,
            'checked_at' => Carbon::now()->toDateString(),
        ];

        /* `CupidaCatalog::portrait()` checks the disk as well as the row, so a
           photo the metadata has just stopped claiming still shows on a card
           for as long as the file is there. A re-resolve that downgrades a
           matched author has to take the face away with the row, the way
           `reject()` does. */
        if ($status !== 'matched') {
            Storage::disk(config('cupida.portraits.disk'))->delete("{$slug}." . DownloadPortrait::EXTENSION);
        }
    }

    private function report(CupidaCatalog $catalog): void
    {
        $count = fn(string $status): int => count(array_filter(
            $this->portraits,
            fn(array $e): bool => ($e['status'] ?? null) === $status,
        ));

        $pinned = count(array_filter($this->portraits, fn(array $e): bool => ($e['pinned'] ?? false) === true));

        $this->newLine();
        $this->components->info(sprintf(
            '%d with a face, %d nobody has photographed, %d not a person, %d pinned by hand.',
            $count('matched'),
            $count('no_image') + $count('no_match') + $count('rejected'),
            $count('no_person'),
            $pinned,
        ));

        /* Not while --only is in play: `outstanding()` answers with the asked
           list then, so counting it would report the size of the run that just
           finished as the work remaining. */
        $left = $this->slugsAsked() === [] ? count($this->outstanding($catalog)) : 0;

        if ($left > 0) {
            $this->components->info("{$left} still to look up. Run again with --limit to take another chunk.");
        }

        /* The join key is Str::slug of the shop's own spelling of a name, so a
           shop that re-types "Nesbo, Jo" as "Nesbø, Jo" orphans a reviewed
           face. Keeping it is right -- deleting an afternoon's work on the
           strength of one scrape run is not a trade worth making -- but it has
           to say so. */
        $pool = array_column(array_slice($catalog->authors(), 0, (int)config('cupida.portraits.pool')), 'slug');
        $orphans = array_diff(array_keys($this->portraits), $pool);

        if ($orphans !== []) {
            $this->components->warn(sprintf(
                '%d %s for authors no longer in the top %d (kept, not deleted).',
                count($orphans),
                Str::plural('portrait', count($orphans)),
                (int)config('cupida.portraits.pool'),
            ));
        }

        $this->components->info('Review them: php artisan cupida:portraits:resolve --sheet');
    }

    /**
     * Every face on one page, for the one check no code can make.
     */
    private function sheet(): int
    {
        $path = storage_path('app/private/cupida-portraits.html');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, recursive: true);
        }
        file_put_contents($path, view('cupida.contact-sheet', [
            'portraits' => $this->portraits,
            'disk'      => rtrim((string)config('filesystems.disks.portraits.root'), '/'),
        ])->render());
        $this->components->info("Contact sheet written to {$path}");

        return self::SUCCESS;
    }

    private function pause(): void
    {
        if ($this->delayMicroseconds > 0 && ! app()->runningUnitTests()) {
            usleep($this->delayMicroseconds);
        }
    }
}
