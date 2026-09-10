<?php

namespace App\Actions\Portraits;

use App\Models\Author;
use App\Support\PersonName;
use Illuminate\Support\Str;

/**
 * File a La Cupida portrait on the author's own record, so the public author
 * page shows the same face the card does.
 *
 * The card reads the portraits disk by the shop's slug ("guerriero-leila");
 * the shop's authors table is keyed by the name's slug ("leila-guerriero").
 * The row's `name` is the bridge, tidied the way `Author::named()` tidies it,
 * so the two spellings of one person meet. A name the shop has no record for
 * is left alone: this links, it never creates an author.
 *
 * The credit travels with the file as custom properties. Commons photos are
 * mostly CC BY-SA with attribution required, and the author page has to be
 * able to say who took it without going back to the JSON.
 */
class AttachAuthorPortrait
{
    /**
     * @param  array<string, mixed>  $entry  a matched row out of author-photos.json
     * @param  string  $bytes  the cropped JPEG, as `cupida:portraits:fetch` stores it
     * @param  bool  $replace  file it even over a portrait the author already has
     * @return bool whether a portrait was filed
     */
    public function __invoke(array $entry, string $bytes, bool $replace = false): bool
    {
        $author = $this->authorFor($entry);

        if (! $author instanceof Author) {
            return false;
        }

        if (! $replace && $author->media()->where('collection_name', Author::PORTRAIT_COLLECTION)->exists()) {
            return false;
        }

        $author->addMediaFromString($bytes)
            ->usingFileName((string)$entry['photo'])
            ->withCustomProperties([
                'source' => 'cupida',
                'credit' => array_filter([
                    'artist'      => data_get($entry, 'credit.artist'),
                    'license'     => data_get($entry, 'credit.license'),
                    'license_url' => data_get($entry, 'credit.license_url'),
                    'source_url'  => data_get($entry, 'credit.source_url'),
                ], fn(mixed $value): bool => is_string($value) && filled($value)),
            ])
            ->toMediaCollection(Author::PORTRAIT_COLLECTION);

        return true;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function authorFor(array $entry): ?Author
    {
        $name = $entry['name'] ?? null;

        if (! is_string($name) || blank($name)) {
            return null;
        }

        return Author::query()->firstWhere('slug', Str::slug(PersonName::normalize($name)));
    }
}
