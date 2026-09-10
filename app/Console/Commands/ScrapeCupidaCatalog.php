<?php

namespace App\Console\Commands;

use App\Support\Cupida\CupidaCatalog;
use App\Support\Shop\ShopScraper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds La Cupida's three JSON files off the shop's live site.
 *
 * This is run by hand, when the stock has moved on far enough that the deck
 * feels stale, and the result is committed. Nothing runs it on deploy and
 * nothing runs it in CI: the app only ever reads what is in the repository, so
 * a shop that is down, slow or behind a new challenge can never take the page
 * with it.
 *
 * It is deliberately slow. The shop is a small independent bookseller on shared
 * hosting, and a scrape that hammers it is a scrape that gets the address
 * blocked -- which is exactly what happened while this was being written. The
 * delay between requests is a courtesy and a self-defense at the same time.
 */
#[Description('Rebuild the La Cupida catalog from laanonimalibreria.com')]
#[Signature('cupida:scrape
        {--pages= : Listing pages per subject, defaults to cupida.scrape.pages_per_subject}
        {--delay= : Milliseconds between requests, defaults to cupida.scrape.delay_ms}
        {--details : Also fetch the own page of every book that still has no synopsis}
        {--limit= : Fetch at most this many book pages this run}
        {--subjects= : Only these deck subject codes, comma separated}
        {--fresh : Throw away what is already on disk and start the pool over}
        {--rebuild : Rewrite authors.json from the committed pool, with no requests}')]
class ScrapeCupidaCatalog extends Command
{
    /** @var array<string, array<string, mixed>> keyed by EAN */
    private array $books = [];

    /** @var array<string, array<string, mixed>> keyed by subject code */
    private array $themes = [];

    /** @var array<int, string> subject codes whose children have been read */
    private array $opened = [];

    private int $delayMicroseconds = 0;

    /** How many books were already on disk when this run started. */
    private int $known = 0;

    public function handle(ShopScraper $shop): int
    {
        $this->delayMicroseconds = (int)($this->option('delay') ?? config('cupida.scrape.delay_ms')) * 1000;

        $this->resume();

        if ($this->option('rebuild')) {
            return $this->rebuild();
        }

        try {
            $this->collectThemes($shop);
            $this->collectSpecials($shop);
            $this->collectSubjects($shop);

            if ($this->option('details')) {
                $this->collectDetails($shop);
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            if ($this->books === []) {
                return self::FAILURE;
            }

            $this->components->warn('Writing what was collected before the failure.');
        }

        $this->write();

        return self::SUCCESS;
    }

    /**
     * Rewrite the files from the pool already on disk, asking the shop nothing.
     *
     * `authors()` is a function of books.json, so a change to how it counts or
     * what it puts on a card only reaches a reader once the file is written
     * again -- and a scrape run to get there is an afternoon of traffic to a
     * small bookseller for an answer we already hold. This is how that change
     * ships.
     */
    private function rebuild(): int
    {
        if ($this->books === []) {
            $this->components->error('Nothing on disk to rebuild from. --rebuild cannot be used with --fresh.');

            return self::FAILURE;
        }

        $this->write();

        return self::SUCCESS;
    }

    /**
     * Pick up where the last run left off.
     *
     * The pool is built over several sittings rather than one: the shop is a
     * small bookseller on shared hosting, the synopsis pass costs a request per
     * book, and a run that tried to fetch everything would be an afternoon of
     * traffic and would get the address blocked. So each run reads what is
     * already on disk, adds to it, and re-fetches nothing it already has.
     *
     * `--fresh` is the way to start over, and is the only way a book the shop
     * has stopped stocking leaves the pool.
     */
    private function resume(): void
    {
        if ($this->option('fresh')) {
            $this->components->warn('Starting the pool over.');

            return;
        }

        $catalog = app(CupidaCatalog::class);

        foreach ($catalog->books() as $book) {
            $this->books[(string)$book['ean']] = [
                ...$book,
                'author' => self::canonicalAuthor($book['author'] ?? null),
            ];
        }

        foreach ($catalog->themes() as $theme) {
            $this->themes[(string)$theme['code']] = $theme;

            /* Children already read last time, so this run walks down to a new
               deck subject without asking again for the levels it shares. */
            if ($theme['parent'] !== null) {
                $this->opened[] = (string)$theme['parent'];
            }
        }

        $this->opened = array_values(array_unique($this->opened));
        $this->known = count($this->books);

        if ($this->known > 0) {
            $this->components->info("Resuming on {$this->known} books. Pass --fresh to start over.");
        }
    }

    /**
     * The subject codes the deck draws its first round from.
     *
     * Only the branches a deck card needs are walked. The tree is a couple of
     * thousand codes and the deck uses fifty of them, so reading all of it
     * would be several hundred requests to throw away.
     *
     * Fifty rather than the eighteen this was written for, and the descent is
     * what absorbs it: each code walks down from the deepest ancestor already
     * known, so the thirty-two narrow codes added to the deck sit under
     * branches the first eighteen have already opened. The listing pass in
     * `collectSubjects()` is the part that grew -- fifty subjects at
     * `pages_per_subject` pages each rather than eighteen -- so take a run in
     * bites with `--subjects` rather than asking the shop for all of it at
     * once.
     */
    private function collectThemes(ShopScraper $shop): void
    {
        /** @var array<string, string> $wanted */
        $wanted = config('cupida.deck.subjects');

        $this->components->task('Reading the subject index', function() use ($shop, $wanted): void {
            $this->record($shop->subjects(), parent: null, wanted: $wanted);
            $this->pause();

            /*
             | Walk down towards each wanted code rather than walking the tree.
             |
             | THEMA nests by prefix and the deck reaches three levels in
             | (JBSF, feminismos, sits under JB, which sits under J), while the
             | whole tree is a couple of thousand codes. So each wanted code
             | pulls in only its own line of descent: find the deepest ancestor
             | already known, fetch that one's children, and go again until the
             | code turns up or the shop stops offering a way down.
             */
            foreach (array_keys($wanted) as $code) {
                $this->descendTo($shop, (string)$code, $wanted);
            }
        });

        $missing = array_diff(array_keys($wanted), array_keys($this->themes));

        if ($missing !== []) {
            $this->components->warn('Deck subjects the shop did not list: ' . implode(', ', $missing));
        }
    }

    /**
     * Fetch children, level by level, until this code is known or cannot be.
     *
     * @param  array<string, string>  $wanted
     */
    private function descendTo(ShopScraper $shop, string $code, array $wanted): void
    {
        while (! isset($this->themes[$code])) {
            $ancestor = $this->deepestUnopenedAncestor($code);

            if ($ancestor === null) {
                return;
            }

            $this->opened[] = $ancestor;

            $this->record(
                $shop->childrenOf($ancestor, (string)$this->themes[$ancestor]['slug']),
                parent: $ancestor,
                wanted: $wanted,
            );

            $this->pause();
        }
    }

    /**
     * The longest known code that this one sits under and whose children have
     * not been read yet.
     */
    private function deepestUnopenedAncestor(string $code): ?string
    {
        $best = null;

        foreach (array_keys($this->themes) as $known) {
            if ($known === $code || ! str_starts_with($code, (string)$known)) {
                continue;
            }

            if (in_array($known, $this->opened, true)) {
                continue;
            }

            if ($best === null || strlen((string)$known) > strlen($best)) {
                $best = (string)$known;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, string>  $subjects  code => heading
     * @param  array<string, string>  $wanted
     */
    private function record(array $subjects, ?string $parent, array $wanted): void
    {
        foreach ($subjects as $code => $heading) {
            $this->themes[$code] ??= [
                'code'       => $code,
                'shop_label' => $heading,
                'label'      => $wanted[$code] ?? $heading,
                'parent'     => $parent,
                'slug'       => Str::slug($heading),
                'deck'       => isset($wanted[$code]),
                'books'      => 0,
            ];
        }
    }

    /**
     * The shop's own curated shelves, taken whole.
     *
     * These carry no subject code, so the books land in the pool with whatever
     * the `--details` pass finds. They are worth having anyway: a bookseller
     * chose them, which is more signal than another page of a subject listing.
     */
    private function collectSpecials(ShopScraper $shop): void
    {
        foreach ((array)config('cupida.scrape.specials') as $special) {
            $this->components->task("Reading {$special}", function() use ($shop, $special): void {
                $this->walk($shop, rtrim((string)$special, '/') . '/', pages: 1, subject: null);
            });
        }
    }

    private function collectSubjects(ShopScraper $shop): void
    {
        $pages = (int)($this->option('pages') ?? config('cupida.scrape.pages_per_subject'));
        $only = $this->only();

        foreach ($this->themes as $code => $theme) {
            if ($theme['deck'] !== true) {
                continue;
            }

            if ($only !== [] && ! in_array((string)$code, $only, true)) {
                continue;
            }

            $this->components->task("Reading {$theme['label']} ({$code})", function() use ($shop, $code, $theme, $pages): void {
                $this->walk($shop, "materia/{$code}/{$theme['slug']}/", $pages, $code);
            });
        }
    }

    /**
     * The subject codes this run was asked to read, if it was asked for any.
     *
     * A deep run over every subject is a few thousand requests; taking three or
     * four at a time is how the pool gets built without the shop noticing.
     *
     * @return array<int, string>
     */
    private function only(): array
    {
        $only = $this->option('subjects');

        if (! is_string($only) || blank($only)) {
            return [];
        }

        return array_values(array_filter(array_map(
            trim(...),
            explode(',', strtoupper($only)),
        )));
    }

    /**
     * Walk a listing, following the shop's own next-page link.
     */
    private function walk(ShopScraper $shop, string $path, int $pages, ?string $subject): void
    {
        $next = $path;

        for ($page = 0; $page < $pages && $next !== null; $page++) {
            $listing = $shop->listing($next);
            $this->pause();

            if ($subject !== null && $page === 0) {
                $this->themes[$subject]['books'] = $listing['total'];
            }

            foreach ($listing['books'] as $book) {
                $this->remember($book, $subject);
            }

            $next = $listing['next'];
        }
    }

    /**
     * @param  array<string, mixed>  $book
     */
    private function remember(array $book, ?string $subject): void
    {
        $ean = (string)$book['ean'];

        $existing = $this->books[$ean] ?? null;

        $subjects = $existing['subjects'] ?? [];

        if ($subject !== null) {
            $subjects[] = $subject;
        }

        $this->books[$ean] = [
            ...$book,
            'ean'       => $ean,
            'author'    => self::canonicalAuthor($book['author'] ?? null),
            'subjects'  => array_values(array_unique($subjects)),
            'synopsis'  => $existing['synopsis'] ?? null,
            'publisher' => $existing['publisher'] ?? null,
        ];
    }

    /**
     * The second pass: one request per book for the synopsis.
     *
     * The synopsis is what the shortlist scores mood cards against and what the
     * model reads before it writes a pitch, so a pool without it recommends by
     * subject code alone. It is also the expensive half of the scrape -- one
     * request per book rather than per thirty-six -- which is why it is opt-in,
     * why it only ever looks at books that do not have one yet, and why
     * `--limit` exists to take a bite of the remainder rather than all of it.
     */
    private function collectDetails(ShopScraper $shop): void
    {
        $books = array_filter(
            $this->books,
            fn(array $book): bool => blank($book['synopsis'] ?? null),
        );

        $outstanding = count($books);
        $limit = $this->option('limit');

        if ($limit !== null) {
            $books = array_slice($books, 0, (int)$limit, preserve_keys: true);
        }

        if ($books === []) {
            $this->components->info('Every book already has a synopsis.');

            return;
        }

        $this->components->info(sprintf(
            'Fetching %d of %d books still without a synopsis.',
            count($books),
            $outstanding,
        ));

        $bar = $this->output->createProgressBar(count($books));
        $bar->start();

        foreach ($books as $ean => $book) {
            try {
                $detail = $shop->book($ean, (string)$book['slug']);
            } catch (Throwable $exception) {
                $this->newLine();
                $this->components->warn("{$ean}: {$exception->getMessage()}");
                $detail = null;
            }

            $this->pause();
            $bar->advance();

            if ($detail === null) {
                continue;
            }

            $this->books[$ean] = [
                ...$book,
                'synopsis'  => Str::limit((string)$detail['synopsis'], (int)config('cupida.synopsis_limit')),
                'publisher' => $detail['publisher'],
                'pages'     => $detail['pages'],
                'year'      => $detail['year'],
                'subjects'  => array_values(array_unique([...$book['subjects'], ...$detail['subjects']])),
            ];
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function write(): void
    {
        $directory = (string)config('cupida.data_path');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, recursive: true);
        }

        $books = array_values($this->books);

        $this->put("{$directory}/themes.json", array_values($this->themes));
        $this->put("{$directory}/authors.json", $this->authors($books));
        $this->put("{$directory}/books.json", $books);

        $missing = count(array_filter(
            $books,
            fn(array $book): bool => blank($book['synopsis'] ?? null),
        ));

        $this->components->info(sprintf(
            '%d books (%d new), %d authors, %d subjects.',
            count($books),
            count($books) - $this->known,
            count($this->authors($books)),
            count($this->themes),
        ));

        if ($missing > 0) {
            $this->components->warn(sprintf(
                '%d books still have no synopsis. Run again with --details to fetch more.',
                $missing,
            ));
        }
    }

    /**
     * The shop's spelling of a name, corrected where we know it is wrong.
     *
     * Applied on both ways in -- the listing and the pool already on disk --
     * because a correction added to the config has to reach the five thousand
     * books we are not going to fetch again. `cupida:scrape --rebuild` is what
     * carries it there.
     *
     * It rewrites books.json rather than only authors.json on purpose:
     * `CupidaShortlist::authorOf()` slugs this string to match a book against a
     * liked author card, so a name corrected in one file and not the other is a
     * card that scores none of its own books.
     */
    private static function canonicalAuthor(?string $name): ?string
    {
        if (! is_string($name) || blank($name)) {
            return null;
        }

        /** @var array<string, string> $aliases */
        $aliases = (array)config('cupida.scrape.author_aliases');

        return $aliases[$name] ?? $name;
    }

    /**
     * The authors deck, built out of the pool rather than fetched.
     *
     * The shop writes a name as "Guerriero, Leila". That is right for a listing
     * sorted by surname and wrong on a card, so it is turned round here and the
     * shop's form is kept for matching.
     *
     * This rebuilds the whole array from the books pool on every run, which is
     * why the author portraits are a separate file and not a key in here: a
     * `photo` written into authors.json would survive exactly until the next
     * scrape and then disappear, with nothing thrown and nothing logged. See
     * `cupida:portraits:resolve` and resources/data/cupida/author-photos.json.
     *
     * `books` counts a writer's distinct titles, not the rows filed under
     * their name. The shop stocks a novel in hardback, paperback and an
     * illustrated edition, which is three rows and one book -- counting rows
     * put James Islington (two novels, four editions) above writers with three
     * of their own, and `cupida.deck.author_min_books` is a floor that has to
     * mean something.
     *
     * `titles` is what a card says under the name, and it is ordered by how
     * many copies the shop has of each rather than by which row the scrape met
     * first. Depth of stock is the only signal a listing carries about which of
     * a writer's books is the one somebody might have heard of, and it is a
     * real one: it changed the subtitle on 203 of the 685 writers the floor
     * lets through, and it is why García Márquez's card reads "Cien años de
     * soledad" rather than "Cien años de soledad (edición ilustrada)", and Mr
     * Tan's opens his series at volume one. Ties break on the title itself, so
     * a re-scrape meeting the listing in another order cannot silently reword
     * every card.
     *
     * @param  array<int, array<string, mixed>>  $books
     * @return array<int, array<string, mixed>>
     */
    private function authors(array $books): array
    {
        $authors = [];

        foreach ($books as $book) {
            $name = $book['author'] ?? null;
            $title = $book['title'] ?? null;

            if (! is_string($name) || blank($name) || ! is_string($title) || blank($title)) {
                continue;
            }

            $authors[$name] ??= [
                'name'      => CupidaCatalog::readableName($name),
                'shop_name' => $name,
                'slug'      => Str::slug($name),
                'titles'    => [],
            ];

            $authors[$name]['titles'][$title] = ($authors[$name]['titles'][$title] ?? 0) + 1;
        }

        $authors = array_map(self::counted(...), array_values($authors));

        usort($authors, fn(array $a, array $b): int => [$b['books'], $a['name']] <=> [$a['books'], $b['name']]);

        return $authors;
    }

    /**
     * One author's titles turned into the card's subtitle and their book count.
     *
     * @param  array<string, mixed>  $author
     * @return array<string, mixed>
     */
    private static function counted(array $author): array
    {
        /** @var array<string, int> $copies */
        $copies = $author['titles'];
        $titles = array_keys($copies);

        usort($titles, fn(string $a, string $b): int => [$copies[$b], $a] <=> [$copies[$a], $b]);

        return [
            ...$author,
            'titles' => array_slice($titles, 0, 3),
            'books'  => count($copies),
        ];
    }

    /**
     * @param  array<int, mixed>  $data
     */
    /**
     * Write beside the file and move it into place.
     *
     * A run takes minutes and the site is up throughout it, so a reader can ask
     * for a recommendation in the middle of one. `file_put_contents` truncates
     * first and fills after, which leaves a window where the catalog is half
     * a JSON document; a rename on the same filesystem has no such window.
     *
     * @param  array<int, mixed>  $data
     */
    private function put(string $path, array $data): void
    {
        $temporary = $path . '.writing';

        file_put_contents(
            $temporary,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        rename($temporary, $path);

        $this->components->twoColumnDetail(Str::after($path, base_path() . '/'), (string)count($data));
    }

    private function pause(): void
    {
        if ($this->delayMicroseconds > 0 && ! app()->runningUnitTests()) {
            usleep($this->delayMicroseconds);
        }
    }
}
