<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * What a book is about: one node of THEMA, the subject scheme the shop
 * classifies its stock with, in the Spanish the shop itself publishes.
 *
 * The tree is a tree, but hardly anything walks it, because **a code is its own
 * path**. FMM sits under FM sits under F, spelled out in the code itself, so
 * every subject a shelf could mean is a prefix match: `withinTree('FM')` is
 * `code like 'FM%'`, left-anchored, served by the unique index, no recursion.
 * `parent_id` exists to render the tree in a form, not to search it.
 *
 * Seeded from `database/seeders/data/subjects.json`, written by
 * `php artisan books:import-subjects`.
 *
 * The ISBN lookup deliberately does not fill it. Google Books answers with free
 * text and no code ("Fiction / Fantasy"), and Open Library's subjects are
 * reader-contributed tags -- one record yields "Girls", "Time", "tortoises" --
 * so neither can be trusted to name a THEMA code. Which materia a book belongs
 * to stays a bookseller's judgment; the providers' own words survive untouched
 * in `books.raw_metadata`.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property int|null $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subject|null $parent
 * @property-read Collection<int, Subject> $children
 * @property-read Collection<int, Book> $books
 */
#[Fillable(['code', 'name', 'slug', 'parent_id'])]
#[RouteKey('slug')]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    /** What separates the levels when a subject writes out where it sits. */
    public const string SEPARATOR = ' › ';

    protected static function booted(): void
    {
        static::saving(function(self $subject): void {
            if (blank($subject->slug)) {
                $subject->slug = Str::slug("{$subject->code} {$subject->name}");
            }
        });
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Subject, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    /**
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * Everything filed here or anywhere below it.
     *
     * The whole reason the code is stored as a code: `FM%` is left-anchored, so
     * this is an index range scan rather than a recursive walk, and it holds for
     * Postgres and SQLite alike -- which matters, because the suite runs on the
     * second and production on the first.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withinTree(Builder $query, string $code): void
    {
        $query->where('code', 'like', $code . '%');
    }

    /**
     * Fills in the `parent` chain `path()` walks, for the whole result at once.
     *
     * Left alone, that walk is lazy: one query per level per subject, which the
     * panel reports as an N+1 on a single record. Nesting `with('parent.parent
     * ...')` only trades it for a query per level. But a code is its own path --
     * the ancestors of `FMM` are the rows whose code is `F` or `FM` -- so every
     * ancestor of every subject in the result is one `whereIn` on the codes,
     * and the relations are set from what comes back. One query, whatever the
     * depth and however many subjects were asked for.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withAncestors(Builder $query): void
    {
        $query->afterQuery(static fn(Collection $subjects) => self::linkAncestors($subjects));
    }

    /**
     * Sets `parent` all the way up on each of these, from one lookup.
     *
     * @param  Collection<int, self>  $subjects
     */
    private static function linkAncestors(Collection $subjects): void
    {
        $codes = $subjects->flatMap(fn(self $subject): array => $subject->ancestorCodes())->unique();

        if ($codes->isEmpty()) {
            $subjects->each(fn(self $subject) => $subject->setRelation('parent', null));

            return;
        }

        /** @var Collection<string, self> $ancestors */
        $ancestors = self::query()->whereIn('code', $codes)->get()->keyBy('code');

        $subjects->each(function(self $subject) use ($ancestors): void {
            /** @var \Illuminate\Support\Collection<int, self> $line */
            $line = collect($subject->ancestorCodes())
                ->map(fn(string $code): ?self => $ancestors->get($code))
                ->filter()
                ->push($subject)
                ->values();

            $line->each(fn(self $node, int $index) => $node->setRelation(
                'parent',
                $index === 0 ? null : $line->get($index - 1),
            ));
        });
    }

    /**
     * Every code above this one, root first: `F` then `FM`, for `FMM`.
     *
     * Taken a character at a time rather than a character per level, so a code
     * that ever spent two on a level still names all of its ancestors -- the
     * ones that do not exist simply do not come back from the lookup.
     *
     * @return array<int, string>
     */
    public function ancestorCodes(): array
    {
        if (strlen($this->code) < 2) {
            return [];
        }

        return array_map(
            fn(int $length): string => substr($this->code, 0, $length),
            range(1, strlen($this->code) - 1),
        );
    }

    /**
     * Where this sits, written out: "Ficción y temas afines › Fantasía".
     *
     * Walks `parent`, so load these through `withAncestors()` wherever a path
     * is rendered. It is for a person reading a form, never for a query.
     */
    public function path(): string
    {
        $names = [$this->name];

        for ($node = $this->parent; $node !== null; $node = $node->parent) {
            array_unshift($names, $node->name);
        }

        return implode(self::SEPARATOR, $names);
    }
}
