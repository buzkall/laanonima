<?php

namespace App\Support\Cupida;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The three JSON files under resources/data/cupida, read once.
 *
 * They are written by `php artisan cupida:scrape` off the shop's live site and
 * committed, so this class never makes a request and never touches the
 * database: the page works with the shop down, and a test needs no network.
 *
 * Registered as a singleton in AppServiceProvider, which is what makes "read
 * once" true across a request. The files are a few hundred kilobytes and every
 * recommendation scores the whole pool, so re-decoding them per call is the one
 * thing that would make this page slow.
 */
class CupidaCatalog
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $themes = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $authors = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $books = null;

    /** @var array<string, string>|null */
    private ?array $titles = null;

    /** @var array<string, string>|null */
    private ?array $slugs = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $portraits = null;

    /** @var array<string, true>|null */
    private ?array $portraitFiles = null;

    /** @var array<string, int>|null */
    private ?array $subjectCounts = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function themes(): array
    {
        return $this->themes ??= $this->read('themes');
    }

    /**
     * The subjects a card can be drawn from: the ones in `cupida.deck.subjects`
     * the pool holds enough of.
     *
     * Built from the config and the pool rather than read out of themes.json,
     * which is a record of the shop's own tree and only reaches as far as the
     * scrape walked. A card needs a code, a label and a count, and all three
     * are here already -- the config names the code and the label, and every
     * book in the pool carries its own code -- so a subject can be added to the
     * deck by editing the config alone, with no scrape run and no network.
     *
     * A subject the pool is too thin on is dropped rather than shown: a card
     * that promises "Cocina" and shortlists three books is worse than one card
     * fewer. See `cupida.deck.min_books`.
     *
     * `books` is the count in the pool, not the shop's own total for the
     * subject page. The shop stocks 4,931 books under "Narrativa" and the pool
     * holds 536 of them; the shortlist can only ever offer the 536, so that is
     * the number the card carries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function deckThemes(): array
    {
        $counts = $this->subjectCounts();
        $floor = (int)config('cupida.deck.min_books');

        $themes = [];

        /** @var array<string, string> $subjects */
        $subjects = (array)config('cupida.deck.subjects');

        foreach ($subjects as $code => $label) {
            $books = $counts[(string)$code] ?? 0;

            if ($books < $floor) {
                continue;
            }

            $themes[] = [
                'code'  => (string)$code,
                'label' => $label,
                'books' => $books,
            ];
        }

        return $themes;
    }

    /**
     * What a subject code is called on a card.
     *
     * The config is asked first and themes.json second, because the config is
     * where a subject is added and the scrape only knows the codes it walked.
     * An unknown code answers with itself rather than with nothing: a badge
     * reading "FFL" in the panel is a clue, an empty one is a bug report.
     */
    public function subjectLabel(string $code): string
    {
        /** @var array<string, string> $subjects */
        $subjects = (array)config('cupida.deck.subjects');

        if (isset($subjects[$code])) {
            return $subjects[$code];
        }

        foreach ($this->themes() as $theme) {
            if ((string)($theme['code'] ?? '') === $code) {
                return (string)($theme['label'] ?? $code);
            }
        }

        return $code;
    }

    /**
     * How many books in the pool sit at or below each subject code.
     *
     * THEMA nests by prefix, so a book filed FMR is also a fantasy book and
     * also a fiction book: every prefix of its code is counted once, and a
     * book carrying two codes of one lineage (FK beside FKM, which two thirds
     * of the pool does) is still one book under FK.
     *
     * One pass over the pool for every code at every depth, memoized on the
     * instance beside the pool it counts -- the deck asks for this once per
     * session and the shortlist never does, so it stays well clear of the
     * scoring path.
     *
     * @return array<string, int>
     */
    private function subjectCounts(): array
    {
        if ($this->subjectCounts !== null) {
            return $this->subjectCounts;
        }

        $counts = [];

        foreach ($this->books() as $book) {
            $prefixes = [];

            /** @var array<int, string> $subjects */
            $subjects = is_array($book['subjects'] ?? null) ? $book['subjects'] : [];

            foreach ($subjects as $code) {
                for ($length = 1; $length <= strlen($code); $length++) {
                    $prefixes[substr($code, 0, $length)] = true;
                }
            }

            foreach (array_keys($prefixes) as $prefix) {
                $counts[$prefix] = ($counts[$prefix] ?? 0) + 1;
            }
        }

        return $this->subjectCounts = $counts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function authors(): array
    {
        return $this->authors ??= $this->read('authors');
    }

    /**
     * Every name the shop stocks enough of to be worth a question.
     *
     * This is what `cupida:portraits:resolve` walks, and it is the list with
     * the collectives still in it -- deciding that "Vv. Aa." is not a person is
     * that command's job, and it cannot record a verdict about a name it was
     * never handed. It reaches them without a request; see `isCollectiveName()`.
     *
     * Not memoized: it is a filter over a few thousand rows, and the floor is a
     * config value a test moves.
     *
     * @return array<int, array<string, mixed>>
     */
    public function authorsAboveFloor(): array
    {
        $minimum = (int)config('cupida.deck.author_min_books');

        return array_values(array_filter(
            $this->authors(),
            fn(array $author): bool => (int)$author['books'] >= $minimum,
        ));
    }

    /**
     * The writers a card can actually be dealt for.
     *
     * The same list with the collectives taken out, which is the deck's view of
     * it. The two are one definition apart on purpose: the portraits command
     * must reach a name the deck must not, and any wider gap between them is
     * either an afternoon spent on faces no reader meets or a card dealt for a
     * writer nobody looked up.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dealableAuthors(): array
    {
        return array_values(array_filter(
            $this->authorsAboveFloor(),
            fn(array $author): bool => ! $this->isCollective(
                (string)$author['slug'],
                (string)$author['name'],
            ),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function books(): array
    {
        return $this->books ??= $this->read('books');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function book(string $ean): ?array
    {
        foreach ($this->books() as $book) {
            if ((string)($book['ean'] ?? '') === $ean) {
                return $book;
            }
        }

        return null;
    }

    /**
     * Every title in the pool, by EAN.
     *
     * `book()` scans, which is right for the one lookup a recommendation does.
     * A page of logged sessions asks for a hundred of them -- a session
     * recorded while the covers round was dealt carries a `book:` answer per
     * swipe -- and that is a hundred passes over five thousand books. Memoized
     * on the instance rather than statically, so a swapped catalog takes its
     * titles with it.
     *
     * @return array<string, string>
     */
    public function titles(): array
    {
        return $this->titles ??= array_column($this->books(), 'title', 'ean');
    }

    /**
     * Every slug in the pool, by EAN.
     *
     * The shop's own address for a book is /libros/{ean}/{slug}/, and the slug
     * lives nowhere else: a book it has stopped stocking has no slug here and
     * no page there either.
     *
     * @return array<string, string>
     */
    public function slugs(): array
    {
        return $this->slugs ??= array_column($this->books(), 'slug', 'ean');
    }

    /**
     * Every author we have looked a face up for, by slug.
     *
     * A fourth file beside the three the scrape writes, and deliberately not a
     * key inside authors.json: `ScrapeCupidaCatalog::authors()` rebuilds that
     * array from the books pool on every run, so a photo recorded in there
     * survives exactly until the next scrape and then vanishes with no error.
     *
     * @return array<string, array<string, mixed>>
     */
    public function portraits(): array
    {
        return $this->portraits ??= $this->readMap('author-photos');
    }

    /**
     * The face for an author, if we have one and this machine has the file.
     *
     * Both halves matter. The metadata is committed and the JPEGs are not, so a
     * checkout that has never run `cupida:portraits:fetch` knows perfectly well
     * that Elvira Sastre has a photo and does not have it -- and a card with a
     * broken image is worse than the faceless card the page dealt before any of
     * this existed.
     */
    public function portrait(string $slug): ?CupidaPortrait
    {
        $portrait = CupidaPortrait::fromPool($this->portraits()[$slug] ?? null);

        if (! $portrait instanceof CupidaPortrait) {
            return null;
        }

        return isset($this->portraitFiles()[$portrait->file]) ? $portrait : null;
    }

    /**
     * A collective or a shared pen name, which is never dealt as a card.
     *
     * The shop files an anthology under an author name, so the pool carries
     * "Vv. Aa." and "Varios Autores" alongside real writers, and a byline like
     * Carmen Mola is three people. None of them is a question a reader can
     * answer by swiping, and none of them has a face.
     *
     * Two answers, because there are two kinds. A `no_person` row is somebody's
     * judgment, recorded while reviewing portraits, and it is the only thing
     * that catches a shared pen name with a real Wikidata item behind it. The
     * patterns catch the shop-ism, and they have to be asked here rather than
     * only in `cupida:portraits:resolve`, because that command is run by hand
     * and always lags the pool: a scrape adds writers on the afternoon it runs
     * and the rows arrive whenever somebody sits down to review faces. Without
     * the patterns, "Vv.Aa.12" is a card for the whole of that gap -- and it
     * turns up under a new spelling every time the catalog grows.
     */
    public function isCollective(string $slug, ?string $name = null): bool
    {
        if (($this->portraits()[$slug]['status'] ?? null) === 'no_person') {
            return true;
        }

        return $name !== null && self::isCollectiveName($name);
    }

    /**
     * A name that says "this is not one writer" before anything is asked of it.
     */
    public static function isCollectiveName(string $name): bool
    {
        return Str::is(
            (array)config('cupida.portraits.collective_patterns'),
            Str::lower(Str::ascii($name)),
        );
    }

    /**
     * The portraits actually on disk.
     *
     * One directory read per request answers it for all six cards. A
     * `Storage::exists()` per card would be six round trips the day this disk
     * stops being local.
     *
     * @return array<string, true>
     */
    private function portraitFiles(): array
    {
        return $this->portraitFiles ??= array_fill_keys(
            Storage::disk(config('cupida.portraits.disk'))->files(),
            true,
        );
    }

    /**
     * Whether there is a pool at all.
     *
     * A checkout with no scrape yet is an ordinary state -- a fresh clone that
     * has not pulled the data files, a branch cut before them -- and the page
     * says so rather than throwing out of a render. Missing files are a
     * deployment problem for a human to see, not a 500 for a reader.
     */
    public function isEmpty(): bool
    {
        return $this->books() === [] || $this->deckThemes() === [];
    }

    /**
     * Swipes as a person reads them: "theme:FM" -> "Fantasía".
     *
     * Lives here rather than on `CupidaRecommendation` because both readers of
     * it need the same words: the panel, showing a bookseller what was asked
     * for, and the prompt, telling the model what the reader actually said so
     * that the match line names their answers instead of guessing at them.
     *
     * Anything that no longer resolves falls back to its key rather than
     * disappearing -- a card the catalog has since dropped is exactly the kind
     * of thing worth seeing in the panel.
     *
     * @param  array<int, string>  $answers  as "kind:key"
     * @return array<int, string>
     */
    public function answerLabels(array $answers): array
    {
        return array_map(function(string $answer): string {
            [$kind, $key] = array_pad(explode(':', $answer, limit: 2), 2, '');

            return match ($kind) {
                'theme'  => $this->subjectLabel($key),
                'author' => (string)(collect($this->authors())->firstWhere('slug', $key)['name'] ?? $key),
                'mood'   => (string)__("cupida.moods.{$key}"),
                'book'   => (string)($this->titles()[$key] ?? $key),
                default  => $answer,
            };
        }, $answers);
    }

    /**
     * "Guerriero, Leila" -> "Leila Guerriero".
     *
     * The shop writes every name surname first, which is right for a listing
     * sorted by surname and wrong everywhere a person is being named. Anything
     * that is not a surname-first pair is left exactly as it is: one-word names
     * ("Senlinyu", "Qntm"), collectives, "VV. AA.".
     */
    public static function readableName(string $name): string
    {
        $parts = array_map(trim(...), explode(',', $name, limit: 2));

        return count($parts) === 2 && filled($parts[1])
            ? "{$parts[1]} {$parts[0]}"
            : $name;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function read(string $name): array
    {
        return array_values($this->decode($name));
    }

    /**
     * A file that is a map rather than a list.
     *
     * The three pool files are lists because `cupida:scrape` rebuilds them
     * wholesale. author-photos.json is keyed by slug because it is looked up by
     * slug and corrected by hand.
     *
     * @return array<string, array<string, mixed>>
     */
    private function readMap(string $name): array
    {
        return $this->decode($name);
    }

    /**
     * @return array<array-key, array<string, mixed>>
     */
    private function decode(string $name): array
    {
        $path = rtrim((string)config('cupida.data_path'), '/') . "/{$name}.json";

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string)file_get_contents($path), associative: true);

        if (is_array($decoded)) {
            return $decoded;
        }

        /* A file that is there but will not parse is a different thing from one
           that is absent, and it has to say so. The reader still gets the empty
           page rather than an exception, but a stray character in a committed
           JSON file otherwise reads as "the shop stocks nothing" all the way
           down -- an empty deck, an empty shortlist, and no clue why. */
        Log::warning('La Cupida could not read its catalog.', [
            'file'  => $path,
            'error' => json_last_error_msg(),
        ]);

        return [];
    }
}
