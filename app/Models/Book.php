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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
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
    'contributors', 'authors_line', 'publisher_id', 'imprint', 'collection_name', 'collection_number',
    'published_on', 'published_year', 'edition_number', 'edition_statement', 'country_of_publication',
    'city_of_publication', 'legal_deposit', 'binding', 'pages', 'height_mm', 'width_mm', 'thickness_mm', 'weight_grams',
    'language', 'original_language', 'subjects', 'synopsis', 'back_cover_text', 'cover_source_url',
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
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
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
     * Denormalize the authors out of the contributors JSON so search and sorting
     * run against a plain indexed column rather than a JSON path.
     *
     * @param  array<int, array{name?: string, role?: string}>|null  $contributors
     */
    private static function buildAuthorsLine(?array $contributors): ?string
    {
        $authors = array_filter(
            $contributors ?? [],
            fn(array $contributor): bool => ($contributor['role'] ?? null) === 'autor'
                && filled($contributor['name'] ?? null),
        );

        if ($authors === []) {
            return null;
        }

        return implode(', ', array_map(fn(array $contributor): string => trim($contributor['name']), $authors));
    }
}
