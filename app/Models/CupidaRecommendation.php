<?php

namespace App\Models;

use App\Support\Cupida\CupidaCatalog;
use Database\Factories\CupidaRecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One session of La Cupida: what a reader said yes and no to, and what they
 * were handed.
 *
 * Written for the bookseller rather than for the app -- nothing reads these
 * back to make a recommendation. What they are good for is the question the
 * shop actually has, which is what people are asking for and whether the answers
 * are any good.
 *
 * The book is copied onto the row instead of referenced. Its EAN comes out of
 * the scraped pool, which is rebuilt by hand and drops whatever the shop has
 * stopped stocking, so a row that only held an EAN would slowly stop being
 * readable. `book_id` is the exception and is set only for the books we
 * catalog here too.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $seed
 * @property array<int, string> $likes
 * @property array<int, string> $passes
 * @property string $ean
 * @property string $title
 * @property string|null $author
 * @property int|null $book_id
 * @property string $pitch
 * @property string|null $match_line
 * @property bool $written
 * @property string|null $model
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string|null $cost
 * @property string|null $shortlist_ean
 * @property string|null $shortlist_title
 * @property string|null $shortlist_author
 * @property int|null $shortlist_rank
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Book|null $book
 */
#[Fillable([
    'user_id', 'seed', 'likes', 'passes',
    'ean', 'title', 'author', 'book_id',
    'pitch', 'match_line', 'written', 'model',
    'input_tokens', 'output_tokens', 'cost',
    'shortlist_ean', 'shortlist_title', 'shortlist_author', 'shortlist_rank',
])]
class CupidaRecommendation extends Model
{
    /** @use HasFactory<CupidaRecommendationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'likes'   => 'array',
            'passes'  => 'array',
            'written' => 'boolean',
            'cost'    => 'decimal:6',
        ];
    }

    /**
     * The sessions whose pitch the model actually wrote, as against the ones
     * that fell back to the canned line.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function written(Builder $query): void
    {
        $query->where('written', true);
    }

    /**
     * The sessions where the model handed over the book the scoring had already
     * put at the top of its shortlist, and the ones where it overruled it.
     *
     * Written rows only, on both sides: a canned line *is* the top of the
     * shortlist, so counting it as agreement would flatter the model with every
     * failure, and counting it as disagreement would be worse.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function agreedWithShortlist(Builder $query): void
    {
        $query->written()->where('shortlist_rank', 1);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function overruledShortlist(Builder $query): void
    {
        $query->written()->where('shortlist_rank', '>', 1);
    }

    /**
     * The book the scoring would have handed over on its own.
     *
     * Shown on every card, including the ones where it is the book above it.
     * The question this answers is "what would a reader get with no model in
     * front of it", and that has an answer on every session -- hiding it when
     * the two agree means the only cards that say anything are the ones where
     * they differ, and silence has to be read as agreement.
     *
     * Says so in as many words when it is the same book, rather than printing
     * the title twice.
     */
    public function shortlistPick(): ?string
    {
        if ($this->shortlist_title === null) {
            return null;
        }

        if ($this->ean === $this->shortlist_ean) {
            return (string)__('cupida.admin.shortlist.same');
        }

        return (string)__('cupida.admin.shortlist.instead', [
            'book' => $this->shortlist_author === null
                ? $this->shortlist_title
                : "{$this->shortlist_title} — {$this->shortlist_author}",
        ]);
    }

    /**
     * The tokens behind the cost, for the one question a price raises: whether
     * it was the prompt or the answer that was expensive.
     *
     * Null when nothing was written -- a fallback line costs nothing and has no
     * tokens to show -- rather than a pair of zeroes that would read as a
     * prompt that somehow used none.
     */
    public function tokenLabel(): ?string
    {
        if ($this->input_tokens === null && $this->output_tokens === null) {
            return null;
        }

        return (string)__('cupida.admin.tokens', [
            'input'  => number_format((int)$this->input_tokens, thousands_separator: '.'),
            'output' => number_format((int)$this->output_tokens, thousands_separator: '.'),
        ]);
    }

    /**
     * The cover to draw the row with.
     *
     * Ours when the EAN is also a book we catalog, and the shop's resizer
     * otherwise -- it answers for any EAN it stocks, which every row here is.
     * `url()` leaves an absolute URL alone, so a media-library URL and a
     * storage path both come back as something an `<img>` can use.
     */
    public function coverUrl(): string
    {
        $local = $this->book?->coverUrl('thumb');

        if (filled($local)) {
            return url($local);
        }

        $base = rtrim((string)config('cupida.scrape.base_url'), '/');

        return "{$base}/imagen.php?ean={$this->ean}&ancho=400";
    }

    /**
     * The book on the shop's own site.
     *
     * The address wants the slug as well as the EAN, and the slug lives only in
     * the scraped pool -- so a book the shop has since dropped gets no link,
     * which is right, because the page it would point at has gone with it.
     */
    public function shopUrl(): ?string
    {
        $slug = app(CupidaCatalog::class)->slugs()[$this->ean] ?? null;

        if (blank($slug)) {
            return null;
        }

        $base = rtrim((string)config('cupida.scrape.base_url'), '/');

        return "{$base}/libros/{$this->ean}/{$slug}/";
    }

    /**
     * Who swiped, when anyone was signed in. Usually nobody: the page is open
     * and asks for nothing.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * The likes as a bookseller would read them.
     *
     * "theme:FM" means nothing in a table; "Fantasía" does. Subjects, moods and
     * the covers swiped in the last round all resolve, and an author is already
     * a name once the slug is turned back round -- but anything that no longer
     * resolves falls back to its key rather than disappearing, because a card
     * the catalog has since dropped is exactly the kind of thing worth seeing
     * here.
     *
     * @return array<int, string>
     */
    public function likeLabels(): array
    {
        return $this->labels($this->likes ?? []);
    }

    /**
     * @return array<int, string>
     */
    public function passLabels(): array
    {
        return $this->labels($this->passes ?? []);
    }

    /**
     * @param  array<int, string>  $answers
     * @return array<int, string>
     */
    private function labels(array $answers): array
    {
        $catalog = app(CupidaCatalog::class);

        return array_map(function(string $answer) use ($catalog): string {
            [$kind, $key] = array_pad(explode(':', $answer, limit: 2), 2, '');

            return match ($kind) {
                'theme'  => $catalog->subjectLabel($key),
                'author' => (string)(collect($catalog->authors())->firstWhere('slug', $key)['name'] ?? $key),
                'mood'   => (string)__("cupida.moods.{$key}"),
                'book'   => (string)($catalog->titles()[$key] ?? $key),
                default  => $answer,
            };
        }, $answers);
    }
}
