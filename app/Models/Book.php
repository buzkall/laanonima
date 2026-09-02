<?php

namespace App\Models;

use App\Enums\BookAvailability;
use App\Enums\BookBinding;
use App\Enums\BookLanguage;
use App\Enums\ContributorRole;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $isbn13
 * @property string|null $isbn10
 * @property string|null $ean13
 * @property string $slug
 * @property string|null $external_reference
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $original_title
 * @property string|null $authors_line
 * @property int|null $publisher_id
 * @property string|null $imprint
 * @property string|null $collection_name
 * @property string|null $collection_number
 * @property Carbon|null $published_on
 * @property int|null $published_year
 * @property int|null $edition_number
 * @property string|null $edition_statement
 * @property string $country_of_publication
 * @property string|null $city_of_publication
 * @property string|null $legal_deposit
 * @property BookBinding|null $binding
 * @property int|null $pages
 * @property int|null $height_mm
 * @property int|null $width_mm
 * @property int|null $thickness_mm
 * @property int|null $weight_grams
 * @property BookLanguage $language
 * @property BookLanguage|null $original_language
 * @property array<int, array{scheme?: string, code?: string|null, heading?: string|null}>|null $subjects
 * @property string|null $synopsis
 * @property string|null $back_cover_text
 * @property string|null $cover_source_url
 * @property string|null $cover_color
 * @property int|null $price_cents
 * @property string $vat_rate
 * @property string $currency
 * @property int $stock
 * @property BookAvailability $availability
 * @property bool $is_featured
 * @property bool $is_active
 * @property string|null $metadata_source
 * @property Carbon|null $metadata_synced_at
 * @property array<string, mixed>|null $raw_metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Publisher|null $publisher
 * @property-read Collection<int, BookContributor> $contributors
 * @property-read Collection<int, Author> $authors
 */
#[Fillable([
    'isbn13', 'isbn10', 'ean13', 'slug', 'external_reference', 'title', 'subtitle', 'original_title',
    'authors_line', 'publisher_id', 'imprint', 'collection_name', 'collection_number',
    'published_on', 'published_year', 'edition_number', 'edition_statement', 'country_of_publication',
    'city_of_publication', 'legal_deposit', 'binding', 'pages', 'height_mm', 'width_mm', 'thickness_mm', 'weight_grams',
    'language', 'original_language', 'subjects', 'synopsis', 'back_cover_text', 'cover_source_url', 'cover_color',
    'price_cents', 'vat_rate', 'currency', 'stock', 'availability', 'is_featured', 'is_active',
    'metadata_source', 'metadata_synced_at', 'raw_metadata',
])]
#[RouteKey('slug')]
class Book extends Model implements HasMedia
{
    /**
     * Every picture of a book lives here, ordered: the front, the back, a
     * spread, a detail of the binding. The first one is *the* cover -- what the
     * listing and the shop show -- so reordering in the panel is how a
     * bookseller picks which image leads.
     */
    public const COVERS_COLLECTION = 'covers';

    /** @use HasFactory<BookFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subjects'           => 'array',
            'raw_metadata'       => 'array',
            'binding'            => BookBinding::class,
            'availability'       => BookAvailability::class,
            'language'           => BookLanguage::class,
            'original_language'  => BookLanguage::class,
            'published_on'       => 'date',
            'metadata_synced_at' => 'datetime',
            'is_featured'        => 'boolean',
            'is_active'          => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function(self $book): void {
            if (blank($book->slug)) {
                $book->slug = Str::slug(Str::limit($book->title, 80, '')) . '-' . $book->isbn13;
            }
        });
    }

    /**
     * The title page, one row per person and role, in the order they are
     * written. Ties on position fall back to insertion order so a row filed
     * without one still lands after the ones before it.
     *
     * @return HasMany<BookContributor, $this>
     */
    public function contributors(): HasMany
    {
        return $this->hasMany(BookContributor::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Only the people filed as authors: the ones with a page of their own.
     *
     * @return BelongsToMany<Author, $this>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'book_contributors')
            ->wherePivot('role', ContributorRole::Author->value)
            ->orderByPivot('position')
            ->orderByPivot('id');
    }

    /**
     * @return BelongsTo<Publisher, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /**
     * Covers arrive at whatever size the source served, and are shown a few
     * hundred pixels wide at most, so a thumbnail is generated for every image.
     *
     * The conversion runs inline rather than through the queue: no worker sits
     * in front of the panel, and a bookseller who uploads a cover expects it in
     * the listing on the next page load.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COVERS_COLLECTION)
            ->registerMediaConversions(function(): void {
                $this->addMediaConversion('thumb')
                    ->nonQueued()
                    ->fit(Fit::Contain, 400, 600);

                /* The shelf draws a cover at up to about 300 CSS pixels wide,
                   so `thumb` is right for a plain screen and soft on a dense
                   one. This is that same picture at twice the density, offered
                   as the 2x of a srcset -- x descriptors rather than w, because
                   the size a book is drawn at follows from its millimetres and
                   not from the width of the window. */
                $this->addMediaConversion('retina')
                    ->nonQueued()
                    ->fit(Fit::Contain, 800, 1200);
            });
    }

    /**
     * The image that leads the collection, which is the cover.
     */
    public function cover(): ?Media
    {
        return $this->getFirstMedia(self::COVERS_COLLECTION);
    }

    public function coverUrl(string $conversion = ''): ?string
    {
        return $this->cover()?->getAvailableUrl([$conversion]);
    }

    /**
     * Everything else in the collection: the back, a spread, a detail of the
     * binding. The cover leads and is shown on its own, so it is dropped here.
     *
     * @return MediaCollection<int, Media>
     */
    public function gallery(): MediaCollection
    {
        return $this->getMedia(self::COVERS_COLLECTION)->skip(1)->values();
    }

    /**
     * File a downloaded cover, named after the ISBN so the same book twice over
     * is recognizable on the disk.
     */
    public function addCoverFromString(string $jpeg): Media
    {
        return $this->addMediaFromString($jpeg)
            ->usingFileName("{$this->isbn13}.jpg")
            ->usingName($this->title)
            ->toMediaCollection(self::COVERS_COLLECTION);
    }

    /**
     * Other books on the shelf by any of the same people, so a co-written
     * book is found from either name.
     *
     * @return Collection<int, self>
     */
    public function alsoByAuthors(int $limit = 4): Collection
    {
        $authorIds = $this->authors->modelKeys();

        if ($authorIds === []) {
            return new Collection;
        }

        return $this->alongside($limit)
            ->whereHas('contributors', fn(Builder $query): Builder => $query
                ->whereIn('author_id', $authorIds)
                ->where('role', ContributorRole::Author))
            ->get();
    }

    /**
     * What else we stock from the same imprint.
     *
     * @return Collection<int, self>
     */
    public function fromSamePublisher(int $limit = 3): Collection
    {
        if ($this->publisher_id === null) {
            return new Collection;
        }

        return $this->alongside($limit)
            ->where('publisher_id', $this->publisher_id)
            ->get();
    }

    /**
     * PVP in euros. Storage stays in integer cents.
     */
    public function priceInEuros(): ?float
    {
        return $this->price_cents === null ? null : $this->price_cents / 100;
    }

    /**
     * The names filed under one role, in the order they were entered.
     *
     * @return array<int, string>
     */
    public function contributorNames(ContributorRole|string $role): array
    {
        $role = $role instanceof ContributorRole ? $role : ContributorRole::from($role);

        return $this->contributors
            ->where('role', $role)
            ->map(fn(BookContributor $contributor): string => $contributor->author->name)
            ->values()
            ->all();
    }

    /**
     * What one person did on this book, as labels.
     *
     * A book listed on someone's own page shows its author, which says
     * nothing about why it is there: this is what tells a translator's shelf
     * apart from an author's.
     *
     * @return array<int, string>
     */
    public function rolesFor(Author $author): array
    {
        return $this->contributors
            ->where('author_id', $author->getKey())
            ->map(fn(BookContributor $contributor): string => $contributor->role->getLabel())
            ->values()
            ->all();
    }

    /**
     * Replace the title page with these people, in this order.
     *
     * Names are matched to existing authors by slug, so the same person on
     * two books stays one record. The same name twice under one role is kept
     * once.
     *
     * @param  array<int, array{name: string, role: ContributorRole|string}>  $contributors
     */
    public function syncContributors(array $contributors): void
    {
        $this->contributors()->get()->each->delete();

        $filed = [];
        $position = 0;

        foreach ($contributors as $contributor) {
            if (blank($contributor['name'])) {
                continue;
            }

            $author = Author::named($contributor['name']);
            $role = $contributor['role'] instanceof ContributorRole
                ? $contributor['role']
                : ContributorRole::from($contributor['role']);

            if (isset($filed[$author->id][$role->value])) {
                continue;
            }

            $filed[$author->id][$role->value] = true;

            $this->contributors()->create([
                'author_id' => $author->id,
                'role'      => $role,
                'position'  => ++$position,
            ]);
        }

        $this->unsetRelation('contributors')->unsetRelation('authors');
        $this->syncAuthorsLine();
    }

    /**
     * Rewrite the denormalized authors line from the contributor rows, so
     * search, sorting and the listings run against a plain indexed column
     * rather than a join.
     *
     * Written straight to the row: this runs from a contributor's own model
     * events, and a save on the book here would fire its hooks for nothing.
     */
    public function syncAuthorsLine(): void
    {
        $names = $this->contributors()
            ->with('author')
            ->where('role', ContributorRole::Author)
            ->get()
            ->map(fn(BookContributor $contributor): string => $contributor->author->name);

        $line = $names->isEmpty() ? null : $names->implode(', ');

        if ($line === $this->authors_line) {
            return;
        }

        $this->newQuery()->whereKey($this->getKey())->update(['authors_line' => $line]);
        $this->authors_line = $line;
        $this->syncOriginalAttribute('authors_line');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Every public listing is the same shelf, only filtered differently.
     *
     * What the bookseller has flagged as recommended leads; after that it is by
     * publication date. A record with no date is pushed to the back explicitly,
     * because a NULL sorts first in descending order on Postgres and last on
     * SQLite, and the shelf should not depend on that.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function onShelf(Builder $query): void
    {
        $query->active()
            ->with('media')
            ->orderByDesc('is_featured')
            ->orderByRaw('published_on is null')
            ->orderByDesc('published_on')
            ->orderByDesc('id');
    }

    /**
     * The books that stand on /estanteria.
     *
     * The shelf is not paginated: it is one row a reader scrolls along, and
     * every book on it is a rigid body in a physics loop, so what limits it is
     * the row rather than the page. `site.shelf.on_stage` is that ceiling.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function onStage(Builder $query): void
    {
        $query->onShelf()
            ->withCover()
            ->with('contributors.author')
            ->limit((int)config('site.shelf.on_stage'));
    }

    /**
     * Only the books there is a picture of.
     *
     * The grid on the home page is happy without one -- it sets the title over
     * the book's own colour, which reads as a deliberate cover. The shelf is
     * not: a book there is an object seen from the front, and a blank coloured
     * board among real covers reads as a missing image rather than as a book.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withCover(Builder $query): void
    {
        $query->whereHas('media', fn(Builder $media): Builder => $media->where(
            'collection_name',
            self::COVERS_COLLECTION,
        ));
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /**
     * The start of any "you might also want" list beside this book: what is on
     * the web, this book itself left out, newest first.
     *
     * These lists are not the shelf -- no featured book leads them and a
     * record with no date is not pushed anywhere in particular, because a
     * handful of titles beside a page is a suggestion rather than a listing.
     *
     * @return Builder<self>
     */
    private function alongside(int $limit): Builder
    {
        return self::query()
            ->active()
            ->whereKeyNot($this->getKey())
            ->orderByDesc('published_on')
            ->limit($limit);
    }
}
