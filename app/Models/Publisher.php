<?php

namespace App\Models;

use Database\Factories\PublisherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $website
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'website'])]
class Publisher extends Model implements HasMedia
{
    /**
     * A publisher has exactly one logotype, replaced rather than accumulated.
     */
    public const LOGO_COLLECTION = 'logo';

    /** @use HasFactory<PublisherFactory> */
    use HasFactory, InteractsWithMedia;

    protected static function booted(): void
    {
        static::saving(function(self $publisher) {
            if (blank($publisher->slug)) {
                $publisher->slug = Str::slug($publisher->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Logos are uploaded by hand at whatever size the publisher hands over, so
     * the listing renders a thumbnail rather than the original.
     *
     * The conversion runs inline: there is no worker in front of the panel, and
     * a bookseller who uploads a logo expects to see it in the table straight
     * away. An SVG produces no thumbnail at all -- no image generator here
     * handles one -- and the Filament column falls back to the original.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::LOGO_COLLECTION)
            ->singleFile()
            ->registerMediaConversions(function(): void {
                $this->addMediaConversion('thumb')
                    ->nonQueued()
                    ->fit(Fit::Contain, 240, 240);
            });
    }

    public function logoUrl(string $conversion = ''): ?string
    {
        $logo = $this->getFirstMedia(self::LOGO_COLLECTION);

        return $logo?->getAvailableUrl([$conversion]);
    }

    /**
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
