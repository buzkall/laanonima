<?php

namespace App\Support\Cupida;

/**
 * Turns eighteen swipes into the handful of books the model gets to choose from.
 *
 * This is where the recommendation is actually made. The model picks one of
 * these and writes the pitch, but it never sees the other eight hundred books,
 * so a shortlist that scores badly cannot be rescued by a good pitch -- and a
 * model that goes off the rails still cannot recommend something the shop does
 * not stock.
 *
 * Passes count as well as likes, and they count less: swiping left on Fantasía
 * is weak evidence -- most people pass most cards -- while swiping right is a
 * deliberate act. Weighting them the same makes the shortlist mostly a list of
 * what was not rejected, which is not the same question.
 */
final readonly class CupidaShortlist
{
    private const int LIKED_SUBJECT = 10;
    private const int LIKED_AUTHOR = 25;
    private const int LIKED_MOOD = 4;
    private const int PASSED_SUBJECT = -4;
    private const int PASSED_AUTHOR = -8;

    /*
     | A yes to a book itself is the least abstract thing a reader can say --
     | they looked at a cover and wanted it. It outweighs a genre and edges an
     | author.
     |
     | It is not decisive on its own: a book that was liked still has to survive
     | the rest of the scoring, and its neighbors -- the same writer, the same
     | shelf -- are lifted with it, which is what turns one yes into a shortlist
     | rather than into a foregone conclusion.
     |
     | Nothing deals book cards today -- the covers round is not in the deck --
     | so this only reaches sessions recorded while it was. It is kept because
     | those rows are still scored when the panel asks what the shortlist would
     | have picked, and because the round is meant to come back.
     */
    private const int LIKED_BOOK = 28;
    private const int LIKED_BOOK_AUTHOR = 12;
    private const int LIKED_BOOK_SUBJECT = 6;

    /** A book the shop has on the table beats one it would have to order. */
    private const int IN_STOCK = 2;

    /**
     * Every accent a Spanish catalog actually contains, plus the Catalan and
     * French ones the shop's imprints bring with them.
     */
    private const array ACCENTS = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    /**
     * Score the pool and take the best of it.
     *
     * A liked author is the heaviest weight in the scoring, so left alone the
     * top of the list is that author's whole backlist: three names said yes to
     * in round two and the thirty are twenty books by three people. `perAuthor`
     * caps how many of one writer's books get through, so a liked author is
     * still at the top and the rest of the list is the rest of the shop.
     *
     * `withoutAuthors` leaves a writer out entirely: a reader who was just
     * asked about Thoreau by name should not be offered Walden.
     *
     * @param  array<int, string>  $likes  answers as "kind:key"
     * @param  array<int, string>  $passes
     * @param  array<int, string>  $withoutAuthors  author slugs whose books are left out
     * @param  int|null  $perAuthor  at most this many books by one author; null reads the config
     * @return array<int, array<string, mixed>>
     */
    public function for(
        CupidaCatalog $catalog,
        array $likes,
        array $passes,
        ?int $take = null,
        array $withoutAuthors = [],
        ?int $perAuthor = null,
    ): array {
        $take ??= (int)config('cupida.shortlist');
        $perAuthor ??= (int)config('cupida.shortlist_per_author');

        $liked = $this->split($likes);
        $passed = $this->split($passes);

        $likedBooks = $this->resolve($catalog, $liked['book']);

        $scored = [];

        foreach ($catalog->books() as $book) {
            /* A book that was shown and turned down is out. Nothing else here
               is an exclusion -- a passed genre only costs points -- but a
               reader who has said no to this exact cover should not be handed
               it back as the answer. */
            if (in_array((string)$book['ean'], $passed['book'], true)) {
                continue;
            }

            if ($withoutAuthors !== [] && in_array($this->authorOf($book), $withoutAuthors, true)) {
                continue;
            }

            $score = $this->score($book, $liked, $passed) + $this->bookScore($book, $likedBooks);

            if ($score > 0) {
                $scored[] = ['score' => $score, 'book' => $book];
            }
        }

        /* Nothing scored: every card was passed, or the pool has no overlap
           with what was liked. A shortlist of the shop's own picks is a better
           answer than an empty one -- and the specials are at the head of the
           pool because they were scraped first. */
        if ($scored === []) {
            $rest = array_filter(
                $catalog->books(),
                fn(array $book): bool => $withoutAuthors === []
                    || ! in_array($this->authorOf($book), $withoutAuthors, true),
            );

            return $this->spread($rest, $take, $perAuthor);
        }

        usort($scored, fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return $this->spread(
            array_map(fn(array $entry): array => $entry['book'], $scored),
            $take,
            $perAuthor,
        );
    }

    /**
     * Take the first `$take` books, best first, letting no author through more
     * than `$perAuthor` times.
     *
     * A book with no author on record is never capped: there is nothing to
     * count it against, and "VV. AA." is not one writer.
     *
     * @param  array<int, array<string, mixed>>  $books
     * @return array<int, array<string, mixed>>
     */
    private function spread(array $books, int $take, int $perAuthor): array
    {
        $taken = [];
        $seen = [];

        foreach ($books as $book) {
            $author = $this->authorOf($book);

            if ($author !== '' && $perAuthor > 0) {
                if (($seen[$author] ?? 0) >= $perAuthor) {
                    continue;
                }

                $seen[$author] = ($seen[$author] ?? 0) + 1;
            }

            $taken[] = $book;

            if (count($taken) >= $take) {
                break;
            }
        }

        return $taken;
    }

    /**
     * The book's author as a card key, so it compares with an `author:` answer.
     *
     * @param  array<string, mixed>  $book
     */
    private function authorOf(array $book): string
    {
        return $this->slug(is_string($book['author'] ?? null) ? $book['author'] : '');
    }

    /**
     * What the books a reader said yes to are worth, and what they say about
     * their neighbors.
     *
     * @param  array<string, mixed>  $book
     * @param  array<int, array<string, mixed>>  $likedBooks
     */
    private function bookScore(array $book, array $likedBooks): int
    {
        if ($likedBooks === []) {
            return 0;
        }

        $ean = (string)$book['ean'];
        $score = 0;

        /** @var array<int, string> $subjects */
        $subjects = is_array($book['subjects'] ?? null) ? $book['subjects'] : [];

        foreach ($likedBooks as $liked) {
            if ((string)$liked['ean'] === $ean) {
                $score += self::LIKED_BOOK;

                continue;
            }

            /* Compared as the shop writes them, surname first, which is the
               one form both rows are guaranteed to carry. */
            $author = is_string($book['author'] ?? null) ? $book['author'] : null;

            if ($author !== null && $author === ($liked['author'] ?? null)) {
                $score += self::LIKED_BOOK_AUTHOR;
            }

            /** @var array<int, string> $theirs */
            $theirs = is_array($liked['subjects'] ?? null) ? $liked['subjects'] : [];

            foreach ($theirs as $subject) {
                if ($this->matchesSubject($subjects, $subject)) {
                    $score += self::LIKED_BOOK_SUBJECT;

                    break;
                }
            }
        }

        return $score;
    }

    /**
     * @param  array<int, string>  $eans
     * @return array<int, array<string, mixed>>
     */
    private function resolve(CupidaCatalog $catalog, array $eans): array
    {
        return array_values(array_filter(array_map(
            $catalog->book(...),
            $eans,
        )));
    }

    /**
     * @param  array<string, mixed>  $book
     * @param  array{theme: array<int, string>, author: array<int, string>, mood: array<int, string>, book: array<int, string>}  $liked
     * @param  array{theme: array<int, string>, author: array<int, string>, mood: array<int, string>, book: array<int, string>}  $passed
     */
    private function score(array $book, array $liked, array $passed): int
    {
        $score = 0;

        /** @var array<int, string> $subjects */
        $subjects = is_array($book['subjects'] ?? null) ? $book['subjects'] : [];

        foreach ($liked['theme'] as $code) {
            $score += $this->matchesSubject($subjects, $code) ? self::LIKED_SUBJECT : 0;
        }

        foreach ($passed['theme'] as $code) {
            $score += $this->matchesSubject($subjects, $code) ? self::PASSED_SUBJECT : 0;
        }

        $author = $this->authorOf($book);

        if ($author !== '') {
            $score += in_array($author, $liked['author'], true) ? self::LIKED_AUTHOR : 0;
            $score += in_array($author, $passed['author'], true) ? self::PASSED_AUTHOR : 0;
        }

        $score += $this->moodScore($book, $liked['mood']);

        if (($book['available'] ?? false) === true) {
            $score += self::IN_STOCK;
        }

        return $score;
    }

    /**
     * THEMA codes nest by prefix: a book filed under FMB is fantasy, because
     * FM is. Matching on equality alone throws away most of the tree.
     *
     * Downwards only, and that is the whole reason a narrow card means
     * anything. This once also matched a card against a book filed *above* it,
     * which sounds symmetrical and is not: 504 books in the pool carry the bare
     * code X and 4,941 carry a two-letter one, so "Manga" (XAM) reached every
     * comic in the shop and scored exactly like "Cómic y novela gráfica". Every
     * narrow card collapsed onto its parent that way.
     *
     * Nothing is lost by dropping it, because the broad code is a card too: a
     * book filed at bare X is reached by the X card, which is still dealt.
     * Measured over the pool it changed the count of seventeen of the eighteen
     * cards that predate this by nothing at all -- the exception was
     * "Feminismos" (JBSF), which had been scoring all 527 books filed under
     * "Sociedad" as though a reader had asked for them.
     *
     * @param  array<int, string>  $subjects
     */
    private function matchesSubject(array $subjects, string $code): bool
    {
        return array_any($subjects, fn(string $subject): bool => str_starts_with($subject, $code));
    }

    /**
     * How much a book reads like the moods that were liked.
     *
     * Matched against the synopsis and the title, folded to plain ASCII so
     * "corazon" reaches "corazón". A book with no synopsis simply scores
     * nothing here rather than being penalised: most of the pool has one, and
     * the ones that do not are usually new arrivals.
     *
     * @param  array<string, mixed>  $book
     * @param  array<int, string>  $moods
     */
    private function moodScore(array $book, array $moods): int
    {
        if ($moods === []) {
            return 0;
        }

        $haystack = $this->fold(
            (is_string($book['title'] ?? null) ? $book['title'] : '') . ' ' .
            (is_string($book['synopsis'] ?? null) ? $book['synopsis'] : ''),
        );

        if (trim($haystack) === '') {
            return 0;
        }

        $score = 0;

        foreach ($moods as $mood) {
            /** @var array<int, string> $keywords */
            $keywords = (array)config("cupida.moods.{$mood}", []);

            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $this->fold($keyword))) {
                    $score += self::LIKED_MOOD;

                    /* One hit per mood. A synopsis that says "muerte" nine
                       times is not nine times more about death. */
                    break;
                }
            }
        }

        return $score;
    }

    /**
     * Split "kind:key" answers into a bucket per kind.
     *
     * @param  array<int, string>  $answers
     * @return array{theme: array<int, string>, author: array<int, string>, mood: array<int, string>, book: array<int, string>}
     */
    private function split(array $answers): array
    {
        $split = ['theme' => [], 'author' => [], 'mood' => [], 'book' => []];

        foreach ($answers as $answer) {
            [$kind, $key] = array_pad(explode(':', $answer, limit: 2), 2, '');

            if (isset($split[$kind]) && $key !== '') {
                $split[$kind][] = $key;
            }
        }

        return $split;
    }

    private function slug(string $name): string
    {
        $folded = $this->fold($name);

        return $folded === '' ? '' : trim((string)preg_replace('/[^a-z0-9]+/', '-', $folded), '-');
    }

    /**
     * Lowercase, unaccented, for comparing Spanish prose written by several
     * different publishers' catalog departments.
     *
     * Spelled out rather than done with `Str::ascii()`, which is a general
     * transliterator with a large table behind it. This runs on the title and
     * synopsis of every book in the pool on every recommendation -- a few
     * thousand strings of several hundred characters -- and at that size the
     * general answer costs about seven times what this one does, which is most
     * of the time it takes to make a recommendation. Spanish has a short and
     * closed list of accents; nothing here needs Cyrillic.
     */
    private function fold(string $text): string
    {
        return strtr(mb_strtolower($text), self::ACCENTS);
    }
}
