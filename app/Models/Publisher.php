<?php

namespace App\Models;

use App\Support\PublisherName;
use Database\Factories\PublisherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
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
 * @property Carbon|null $logo_checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'website'])]
#[RouteKey('slug')]
class Publisher extends Model implements HasMedia
{
    /**
     * A publisher has exactly one logotype, replaced rather than accumulated.
     */
    public const LOGO_COLLECTION = 'logo';

    /** @use HasFactory<PublisherFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logo_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function(self $publisher): void {
            if (blank($publisher->slug)) {
                $publisher->slug = Str::slug($publisher->name);
            }
        });
    }

    /**
     * The publisher row for a name off a catalog, filed once.
     *
     * The name is tidied first (see PublisherName): what the shop's listing
     * shouts as "EDICIONES VERSATIL, S.L." is an imprint this site prints as
     * "Ediciones Versatil, S.L.". The slug is taken from the tidied name, which
     * is what closes the duplicate the shop opens by writing the same publisher
     * two ways -- "PLAZA & JANES" and "PLAZA &amp; JANES" both spell
     * `plaza-janes` once the name has been through it.
     */
    public static function named(string $name): self
    {
        $name = PublisherName::normalize($name);

        $publisher = self::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);

        /*
         | A row filed before the name was tidied keeps its slug, and with it
         | every book -- but not its shouting. Only a name written in nothing
         | but capitals is rewritten, which no bookseller types, so this cannot
         | overrule a name somebody corrected by hand.
         */
        $tidied = PublisherName::normalize($publisher->name);

        if ($publisher->name !== $tidied) {
            $publisher->update(['name' => $tidied]);
        }

        return $publisher;
    }

    /**
     * Logos are uploaded by hand at whatever size the publisher hands over, so
     * the listing renders a thumbnail rather than the original.
     *
     * The conversion runs inline: there is no worker in front of the panel, and
     * a bookseller who uploads a logo expects to see it in the table straight
     * away. An SVG produces no thumbnail at all -- no image generator here
     * handles one -- and the Filament column falls back to the original.
     *
     * The thumbnail keeps the original's format because media library encodes
     * every conversion as JPEG otherwise, and a JPEG has no alpha channel: a
     * transparent PNG logotype came out on a black box.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::LOGO_COLLECTION)
            ->singleFile()
            ->registerMediaConversions(function(): void {
                $this->addMediaConversion('thumb')
                    ->nonQueued()
                    ->keepOriginalImageFormat()
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
