<?php

namespace App\Actions\Cupida;

use App\Actions\Books\EnrichImportedBook;
use App\Actions\Books\ImportShopBook;
use App\Ai\Agents\CupidaAgent;
use App\Models\Book;
use App\Models\CupidaRecommendation;
use App\Support\Cupida\CupidaCatalog;
use App\Support\Cupida\CupidaShortlist;
use App\Support\Cupida\PromptCost;
use App\Support\Cupida\Recommendation;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

/**
 * Eighteen swipes in, one book out.
 *
 * Two steps, and the order matters: the shortlist is scored here, in PHP,
 * against the whole pool, and only then does a model see anything. That is what
 * keeps the recommendation honest -- the choice is made from stock, by rules
 * that can be read and tested -- and it is also what keeps it cheap, because
 * the prompt carries thirty books instead of nine hundred.
 *
 * The model is the last step and the optional one. With no key configured, a
 * provider having a bad day, or a rate limit reached, the top of the shortlist
 * is still a good answer and the reader still gets a book; what they lose is
 * the sentence explaining it. A demo that falls back to a canned line is a demo
 * that goes on working in a shop with bad wifi, which is where this one will be
 * shown.
 */
class RecommendBook
{
    public function __construct(
        private CupidaCatalog $catalog,
        private CupidaShortlist $shortlist,
        private WatchCupidaCredit $credit,
        private ImportShopBook $import,
    ) {}

    /**
     * @param  array<int, string>  $likes  answers as "kind:key"
     * @param  array<int, string>  $passes
     */
    public function __invoke(array $likes, array $passes, bool $write = true, ?int $seed = null): ?Recommendation
    {
        $shortlist = $this->shortlist->for($this->catalog, $likes, $passes);

        if ($shortlist === []) {
            return null;
        }

        $recommendation = $this->decide($shortlist, $likes, $passes, $write);

        /* Before the row, never after it: `record()` writes `book_id`, and a
           book filed afterwards would leave every automatically catalogd
           recommendation reading "not in our catalog" in the panel forever. */
        $recommendation = $this->fileLocally($recommendation);

        $this->record($recommendation, $shortlist, $likes, $passes, $seed);

        /* After the row, never before it: what is left is the balance minus
           the rows, so a check that runs first is a check that has not seen the
           dollar just spent. */
        if ($recommendation->written) {
            $this->credit->afterSpending();
        }

        return $recommendation;
    }

    /**
     * @param  array<int, array<string, mixed>>  $shortlist
     * @param  array<int, string>  $likes
     * @param  array<int, string>  $passes
     */
    private function decide(array $shortlist, array $likes, array $passes, bool $write): Recommendation
    {
        if (! $write || ! $this->configured()) {
            return $this->fallback($shortlist);
        }

        try {
            return $this->written($shortlist, $likes, $passes);
        } catch (Throwable $exception) {
            Log::warning('La Cupida could not write a recommendation.', [
                'exception' => $exception->getMessage(),
            ]);

            /* The one failure the shop can do something about, and the only
               moment the balance is known rather than estimated. Everything
               else here is the provider's bad day and nobody's to fix. */
            if ($exception instanceof InsufficientCreditsException) {
                $this->credit->afterRefusal();
            }

            return $this->fallback($shortlist);
        }
    }

    /**
     * Put the book on our own shelf, so the reader is sent to our page for it.
     *
     * The shop's pool is five thousand books and this site's catalog is a small
     * part of it, so left alone almost every reader is handed off to the old
     * site at the moment the page has earned their attention. The pool entry
     * carries everything a `books` row needs, so filing it costs a handful of
     * queries and no network at all -- it fits inside the wait the reader is
     * already spending on the model, and nothing on the page has to wait for it
     * or poll for it afterwards.
     *
     * What the free ISBN sources can add -- binding, measurements, a cover --
     * is three providers with a five-second timeout apiece, so it is deferred
     * past the response. Nobody is waiting on it: the result panel is drawn
     * once and the book page reads fine with a title over a flat color until
     * the cover lands.
     *
     * Every failure here is swallowed. A book that could not be filed is a
     * reader sent to the shop's page, which is where they were going anyway.
     */
    private function fileLocally(Recommendation $recommendation): Recommendation
    {
        if ($recommendation->book instanceof Book || ! $this->mayImport()) {
            return $recommendation;
        }

        $entry = $this->catalog->book($recommendation->ean);

        if ($entry === null) {
            return $recommendation;
        }

        try {
            $book = ($this->import)($entry);
        } catch (Throwable $exception) {
            Log::warning('La Cupida could not file a recommended book.', [
                'ean'       => $recommendation->ean,
                'exception' => $exception->getMessage(),
            ]);

            return $recommendation;
        }

        if (! $book instanceof Book) {
            return $recommendation;
        }

        defer(fn() => app(EnrichImportedBook::class)($book));

        return $recommendation->withLocalBook($book);
    }

    /**
     * The page is public and unauthenticated, and `cupida.rate_limit` only
     * guards the paid pitch -- a reader past it still gets a book. So without a
     * ceiling of its own, a script walking the deck could put the shop's whole
     * catalog on our shelf in an afternoon, unreviewed.
     *
     * Counted off the column rather than a cache key: the question is how many
     * records were created today, and the table already answers it.
     */
    private function mayImport(): bool
    {
        if (! config('cupida.import.enabled')) {
            return false;
        }

        return Book::query()
            ->where('metadata_source', ImportShopBook::SOURCE)
            ->whereDate('created_at', today())
            ->count() < (int)config('cupida.import.daily_cap');
    }

    /**
     * Keep what was asked and what was answered, for the bookseller.
     *
     * Nothing reads these back to make a recommendation -- they are there so
     * the shop can see what people are asking for, whether the answers are any
     * good, and what the asking cost. A failure to write one must never cost a
     * reader their book, so it is logged and swallowed.
     *
     * The shortlist's own pick is kept beside the model's, which costs nothing
     * -- it is `$shortlist[0]`, already scored before anything was prompted --
     * and is the only way to ask afterwards whether the model was worth the
     * money. `shortlist_rank` is where its choice sat in the thirty: a column
     * of ones is a model agreeing with the scoring often enough to wonder why
     * it is being asked.
     *
     * @param  array<int, array<string, mixed>>  $shortlist
     * @param  array<int, string>  $likes
     * @param  array<int, string>  $passes
     */
    private function record(Recommendation $recommendation, array $shortlist, array $likes, array $passes, ?int $seed): void
    {
        $top = $shortlist[0];

        try {
            CupidaRecommendation::query()->create([
                'user_id'    => auth()->id(),
                'seed'       => $seed ?? 0,
                'likes'      => array_values($likes),
                'passes'     => array_values($passes),
                'ean'        => $recommendation->ean,
                'title'      => $recommendation->title,
                'author'     => $recommendation->author,
                'book_id'    => $recommendation->book?->id,
                'pitch'      => $recommendation->pitch,
                'match_line' => $recommendation->matchLine,
                'written'    => $recommendation->written,
                /* Whatever answered, not whatever was asked for. Null on the
                   fallback path, where nothing answered at all. */
                'model'         => $recommendation->cost?->model,
                'input_tokens'  => $recommendation->cost?->inputTokens,
                'output_tokens' => $recommendation->cost?->outputTokens,
                'cost'          => $recommendation->cost?->usd,

                'shortlist_ean'    => (string)$top['ean'],
                'shortlist_title'  => (string)$top['title'],
                'shortlist_author' => is_string($top['author'] ?? null)
                    ? CupidaCatalog::readableName($top['author'])
                    : null,
                'shortlist_rank' => $this->rankOf($shortlist, $recommendation->ean),
            ]);
        } catch (Throwable $exception) {
            Log::warning('La Cupida could not keep a recommendation.', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $shortlist
     * @param  array<int, string>  $likes
     * @param  array<int, string>  $passes
     */
    private function written(array $shortlist, array $likes, array $passes): Recommendation
    {
        /* Labels, not "theme:FM". The agent is writing a sentence a reader will
           read, and the words it needs are the ones that were on the cards. */
        $agent = new CupidaAgent(
            shortlist: $shortlist,
            likes: $this->catalog->answerLabels($likes),
            passes: $this->catalog->answerLabels($passes),
        );

        $response = $agent->prompt(
            $this->promptFor($agent),
            model: (string)config('cupida.model'),
            timeout: (int)config('cupida.timeout'),
        );

        /* A structured agent is answered with a structured response, but the
           trait's signature only promises an AgentResponse -- and a provider
           that ignored the schema would hand back exactly that. Treating a
           plain response as a failed write is right either way: the reader gets
           the top of the shortlist rather than a blank pitch. */
        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The provider answered without the structured output that was asked for.');
        }

        $answer = $response->structured;

        $chosen = $this->find($shortlist, (string)($answer['ean'] ?? ''));

        /* The schema pins `ean` to the shortlist, so a miss here means the
           provider ignored the enum rather than that the model chose badly.
           Take the top of the shortlist and keep the prose. */
        $chosen ??= $shortlist[0];

        return Recommendation::make(
            book: $chosen,
            pitch: trim((string)($answer['pitch'] ?? '')) ?: (string)__('cupida.result.fallback_pitch'),
            matchLine: trim((string)($answer['match_line'] ?? '')) ?: null,
            written: true,
            cost: PromptCost::of($response->usage, $response->meta),
        );
    }

    /**
     * The answers first and the books second, because that is the order the
     * question is asked in: this is what they said, now pick from these.
     *
     * The line that introduces them says what they are for, because the list on
     * its own reads as something to hand back -- and was handed back, as
     * "porque dijiste que sí a X, a Y y a Z". The rule is in
     * `CupidaAgent::baseInstructions()`; this is the same rule where the
     * temptation actually sits.
     */
    private function promptFor(CupidaAgent $agent): string
    {
        $answers = $agent->answers();

        $books = "Estos son los libros entre los que puedes elegir:\n\n{$agent->catalog()}";

        return $answers === ''
            ? $books
            : "Esto es lo que ha respondido, y es para que elijas tú, no para recitárselo:\n\n{$answers}\n\n{$books}";
    }

    /**
     * The best-scoring book with the canned line, for when nothing writes.
     *
     * @param  array<int, array<string, mixed>>  $shortlist
     */
    private function fallback(array $shortlist): Recommendation
    {
        return Recommendation::make(
            book: $shortlist[0],
            pitch: (string)__('cupida.result.fallback_pitch'),
            matchLine: null,
            written: false,
        );
    }

    /**
     * Where in the shortlist the recommended book sat, counting from one.
     *
     * Null only if the book is not in the list at all, which the schema's enum
     * makes impossible -- but a rank invented for a book that was not offered
     * would be worse than an empty column.
     *
     * @param  array<int, array<string, mixed>>  $shortlist
     */
    private function rankOf(array $shortlist, string $ean): ?int
    {
        foreach (array_values($shortlist) as $index => $book) {
            if ((string)$book['ean'] === $ean) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $shortlist
     * @return array<string, mixed>|null
     */
    private function find(array $shortlist, string $ean): ?array
    {
        foreach ($shortlist as $book) {
            if ((string)$book['ean'] === $ean) {
                return $book;
            }
        }

        return null;
    }

    /**
     * Whether there is anything to prompt.
     *
     * Checked rather than caught, because a missing key is the ordinary state
     * of a fresh checkout and of the test suite -- it is not worth an exception
     * and a log line every time somebody swipes.
     */
    private function configured(): bool
    {
        $provider = (string)config('ai.default');

        return filled(config("ai.providers.{$provider}.key"));
    }
}
