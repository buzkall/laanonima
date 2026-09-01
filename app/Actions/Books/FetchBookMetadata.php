<?php

namespace App\Actions\Books;

use App\Support\BookMetadata\BookMetadata;
use App\Support\BookMetadata\BookMetadataProvider;
use App\Support\Isbn;
use Illuminate\Support\Facades\Cache;

/**
 * The single entry point for "what do we know about this ISBN?".
 *
 * Used by the Filament resource, the seeder, and whatever imports the catalogue
 * once DILVE credentials arrive.
 */
class FetchBookMetadata
{
    public function __construct(private BookMetadataProvider $provider) {}

    public function __invoke(?string $isbn): ?BookMetadata
    {
        $isbn13 = Isbn::toIsbn13($isbn);

        if ($isbn13 === null) {
            return null;
        }

        /*
         | Cached as a plain array, never as the DTO: config/cache.php forbids
         | unserializing classes out of the cache. A miss is cached too, as an
         | empty array, so a Spanish ISBN neither source knows about does not
         | mean a round trip on every keystroke.
         |
         | The key carries a version. The cached payload is the DTO's own array
         | shape, so adding a field to BookMetadata leaves every live entry
         | silently short of it for a whole TTL -- which is how the physical
         | measurements would have arrived as null for a day. Bump the version
         | whenever that shape changes.
         */
        $cached = Cache::remember(
            "book-metadata:v2:{$isbn13}",
            config('books.metadata.cache_ttl'),
            fn(): array => $this->provider->find($isbn13)?->toArray() ?? [],
        );

        return $cached === [] ? null : BookMetadata::fromArray($cached);
    }
}
