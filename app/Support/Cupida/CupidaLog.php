<?php

namespace App\Support\Cupida;

use App\Ai\Agents\CupidaAgent;
use App\Models\CupidaRecommendation;
use App\Settings\CupidaSettings;
use Illuminate\Database\Eloquent\Builder;

/**
 * The log of what La Cupida has recommended, as a file something else can read.
 *
 * The panel answers "what did it say to this reader"; what it cannot answer is
 * "are these any good", because that is a question about the whole run and not
 * about a card. So the shop takes the run somewhere that can read all of it at
 * once -- which in practice means handing it to a model -- and this is the
 * shape it goes in.
 *
 * JSON rather than a spreadsheet. Half of what makes a session legible is
 * nested (what was swiped, what was handed over, what the scoring would have
 * handed over instead), and flattening that into columns either loses the
 * lists or turns one session into a dozen rows.
 *
 * Three things travel with the sessions and none of them are decoration:
 *
 * - The prompt in effect, both halves. A pitch cannot be judged against nothing
 *   -- "too short", "sounds like an advert" and "not in Spanish from Spain" are
 *   only failures if something asked for the opposite, and the shop's half of
 *   that changes from a modal without a deploy.
 * - The shortlist pick, on every session. Our own scoring is the control: the
 *   question worth asking is not whether the pitches read well but whether the
 *   model earned its price over the book the code had already chosen.
 * - What it cost. Performance and price are the same question here.
 *
 * The swipes go as the labels a bookseller reads and not as the "theme:FM" they
 * are stored as, for the same reason the table shows labels: whatever reads
 * this has to know that a key is a genre without being told.
 */
final readonly class CupidaLog
{
    /**
     * Rows per query while streaming. Small enough that the memory a download
     * holds does not grow with the log, large enough not to make a query per
     * card.
     */
    private const int CHUNK = 200;

    public function __construct(private CupidaSettings $settings) {}

    /**
     * Writes the whole export to the output buffer.
     *
     * Streamed rather than built and returned, because the log only ever grows
     * and a shop that has been running La Cupida for a year should not find out
     * about the memory limit by clicking a button. The envelope is written by
     * hand around a lazy query for the same reason: `json_encode` of everything
     * would need everything in memory first.
     *
     * One session per line, so the file stays greppable and a diff between two
     * exports is readable, and so the paragraph-long pitches do not turn the
     * whole thing into indentation.
     *
     * @param  Builder<CupidaRecommendation>  $query  usually the table's own filtered, sorted query
     */
    public function stream(Builder $query): void
    {
        echo '{' . PHP_EOL;
        echo '  "generated_at": ' . $this->encode(now()->toIso8601String()) . ',' . PHP_EOL;
        echo '  "prompt": ' . $this->encode($this->prompt()) . ',' . PHP_EOL;
        echo '  "summary": ' . $this->encodeReadably($this->summary($query)) . ',' . PHP_EOL;
        echo '  "sessions": [' . PHP_EOL;

        $separator = '';

        /* `lazy()` walks the query with an offset per chunk and keeps whatever
           ordering the table asked for -- which is the point, since the export
           should come out in the order the person exporting it was looking at.
           Offsets over a sort that is not unique can drop or repeat a row, so
           the id goes on the end of it: every sort this table offers has ties,
           and created_at desc has them by the dozen on a busy afternoon. */
        foreach ($query->clone()->with('user')->orderBy('id')->lazy(self::CHUNK) as $recommendation) {
            echo $separator . '    ' . $this->encode($this->session($recommendation));
            $separator = ',' . PHP_EOL;
        }

        echo PHP_EOL . '  ]' . PHP_EOL . '}' . PHP_EOL;
    }

    /**
     * What La Cupida was told to say, in both halves and kept apart.
     *
     * Which half a complaint belongs to is the first thing anyone reading the
     * pitches has to work out, and it is the difference between a note for the
     * next deploy and a line a bookseller can change this afternoon.
     *
     * @return array<string, string|null>
     */
    public function prompt(): array
    {
        return [
            'base'  => new CupidaAgent([])->baseInstructions(),
            'extra' => trim((string)$this->settings->extra_instructions) ?: null,
        ];
    }

    /**
     * The totals over exactly the rows being exported.
     *
     * Arithmetic a model could do for itself over the sessions below, and it is
     * here anyway: these are the numbers every answer will open with, and a
     * count it worked out is a count it can get wrong.
     *
     * Counted rather than summed with a CASE, because `written` is a boolean
     * and the two databases this runs on -- Postgres in production, SQLite in
     * the suite -- do not agree on what one compares to. The scopes also keep
     * the definition of agreement in one place: a canned line is the top of the
     * shortlist by construction and counts as neither side of it.
     *
     * @param  Builder<CupidaRecommendation>  $query
     * @return array<string, mixed>
     */
    public function summary(Builder $query): array
    {
        $sessions = $this->count($query);
        $written = $this->count($query->clone()->written());

        return [
            'sessions'              => $sessions,
            'written'               => $written,
            'fallback'              => $sessions - $written,
            'agreed_with_shortlist' => $this->count($query->clone()->agreedWithShortlist()),
            'overruled_shortlist'   => $this->count($query->clone()->overruledShortlist()),
            /* How much of the catalog the thing actually reaches. A run that
               keeps handing over the same four books is a failure a page of
               well-written pitches hides completely. */
            'distinct_books' => $query->clone()->reorder()->distinct()->count('ean'),
            'cost_usd'       => round((float)$query->clone()->reorder()->sum('cost'), 6),
            'input_tokens'   => (int)$query->clone()->reorder()->sum('input_tokens'),
            'output_tokens'  => (int)$query->clone()->reorder()->sum('output_tokens'),
            'first_session'  => $this->timestamp($query, 'min'),
            'last_session'   => $this->timestamp($query, 'max'),
        ];
    }

    /**
     * One session: what was asked, what was answered, and what it cost.
     *
     * @return array<string, mixed>
     */
    public function session(CupidaRecommendation $recommendation): array
    {
        return [
            'id'          => $recommendation->id,
            'at'          => $recommendation->created_at?->toIso8601String(),
            'reader'      => $recommendation->user?->name,
            'likes'       => $recommendation->likeLabels(),
            'passes'      => $recommendation->passLabels(),
            'recommended' => [
                'ean'        => $recommendation->ean,
                'title'      => $recommendation->title,
                'author'     => $recommendation->author,
                'in_catalog' => $recommendation->book_id !== null,
            ],
            'pitch'         => $recommendation->pitch,
            'match_line'    => $recommendation->match_line,
            'written'       => $recommendation->written,
            'model'         => $recommendation->model,
            'input_tokens'  => $recommendation->input_tokens,
            'output_tokens' => $recommendation->output_tokens,
            'cost_usd'      => $recommendation->cost === null ? null : (float)$recommendation->cost,
            'shortlist'     => [
                'ean'          => $recommendation->shortlist_ean,
                'title'        => $recommendation->shortlist_title,
                'author'       => $recommendation->shortlist_author,
                'rank_of_pick' => $recommendation->shortlist_rank,
                /* Null and not false when nothing was written: the canned line
                   *is* the top of the shortlist, so calling that agreement
                   credits the model with every failure. */
                'agreed' => $recommendation->written ? $recommendation->shortlist_rank === 1 : null,
            ],
        ];
    }

    /**
     * @param  Builder<CupidaRecommendation>  $query
     */
    private function count(Builder $query): int
    {
        return $query->clone()->reorder()->count();
    }

    /**
     * @param  Builder<CupidaRecommendation>  $query
     */
    private function timestamp(Builder $query, string $aggregate): ?string
    {
        $value = $query->clone()->reorder()->{$aggregate}('created_at');

        return blank($value) ? null : now()->parse($value)->toIso8601String();
    }

    /**
     * The one block a person opens the file to read, laid out to be read.
     *
     * Indented to sit under the key it follows: `JSON_PRETTY_PRINT` starts at
     * the first column, which puts the closing brace of the summary against the
     * left margin and the whole envelope out of true.
     */
    private function encodeReadably(mixed $value): string
    {
        /* "\n" and not PHP_EOL: the newline being matched is the one
           `json_encode` writes, which is that one on every platform. */
        return str_replace(
            "\n",
            "\n  ",
            json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            ),
        );
    }

    /**
     * Unescaped, because the whole file is Spanish and `á` in every third
     * word is a file nobody can read and a model spends tokens decoding.
     */
    private function encode(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}
