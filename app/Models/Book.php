<?php

namespace App\Models;

use App\Enums\BookAvailability;
use App\Enums\BookBinding;
use App\Enums\BookLanguage;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property array<int, array{name?: string, role?: string}>|null $contributors
 * @property string|null $authors_line
 * @property array<int, string>|null $author_slugs
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
 */
#[Fillable([
    'isbn13', 'isbn10', 'ean13', 'slug', 'external_reference', 'title', 'subtitle', 'original_title',
    'contributors', 'authors_line', 'author_slugs', 'publisher_id', 'imprint', 'collection_name', 'collection_number',
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
            'contributors'       => 'array',
            'author_slugs'       => 'array',
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
            $book->authors_line = self::buildAuthorsLine($book->contributors);
            $book->author_slugs = self::buildAuthorSlugs($book->contributors);

            if (blank($book->slug)) {
                $book->slug = Str::slug(Str::limit($book->title, 80, '')) . '-' . $book->isbn13;
            }
        });
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
     * Other books on the shelf by any of the same people.
     *
     * Matched on the denormalized authors line rather than the contributors
     * JSON, so a co-written book is found from either name.
     *
     * @return Collection<int, self>
     */
    public function alsoByAuthors(int $limit = 4): Collection
    {
        $authors = $this->contributorNames('author');

        if ($authors === []) {
            return new Collection;
        }

        return $this->alongside($limit)
            ->where(function(Builder $query) use ($authors): void {
                foreach ($authors as $author) {
                    $query->orWhere('authors_line', 'like', '%' . $author . '%');
                }
            })
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
     * The contributor names for a given role, in the order they were entered.
     *
     * @return array<int, string>
     */
    public function contributorNames(string $role): array
    {
        return array_values(array_map(
            fn(array $contributor): string => $contributor['name'],
            array_filter(
                $this->contributors ?? [],
                fn(array $contributor): bool => ($contributor['role'] ?? null) === $role
                    && filled($contributor['name'] ?? null),
            ),
        ));
    }

    /**
     * The authors as name and slug, so each one can be linked to its own page.
     *
     * @return array<int, array{name: string, slug: string}>
     */
    public function authors(): array
    {
        return array_map(
            fn(string $name): array => ['name' => $name, 'slug' => self::authorSlug($name)],
            $this->contributorNames('author'),
        );
    }

    /**
     * How a person's name becomes the last segment of their page's address.
     *
     * There is no authors table: the slug is the key, and `books.author_slugs`
     * is the denormalized index it is looked up in. Everything that builds or
     * resolves an author link goes through here, so the two never drift.
     */
    public static function authorSlug(string $name): string
    {
        return Str::slug($name);
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

    /**
     * The slug of every author, so an author's page is one indexed lookup
     * rather than a scan that has to slug each row in PHP.
     *
     * @param  array<int, array{name?: string, role?: string}>|null  $contributors
     * @return array<int, string>
     */
    private static function buildAuthorSlugs(?array $contributors): array
    {
        return array_values(array_unique(array_map(
            self::authorSlug(...),
            self::authorsAmong($contributors),
        )));
    }

    /**
     * Denormalize the authors out of the contributors JSON so search and sorting
     * run against a plain indexed column rather than a JSON path.
     *
     * @param  array<int, array{name?: string, role?: string}>|null  $contributors
     */
    private static function buildAuthorsLine(?array $contributors): ?string
    {
        $authors = self::authorsAmong($contributors);

        return $authors === [] ? null : implode(', ', $authors);
    }

    /**
     * The names filed as authors, trimmed, in the order they were entered.
     *
     * @param  array<int, array{name?: string, role?: string}>|null  $contributors
     * @return array<int, string>
     */
    private static function authorsAmong(?array $contributors): array
    {
        return array_values(array_map(
            fn(array $contributor): string => trim($contributor['name']),
            array_filter(
                $contributors ?? [],
                fn(array $contributor): bool => ($contributor['role'] ?? null) === 'author'
                    && filled($contributor['name'] ?? null),
            ),
        ));
    }
}
