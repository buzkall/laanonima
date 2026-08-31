<?php

namespace Database\Seeders;

use App\Actions\Books\DownloadBookCover;
use App\Actions\Books\FetchBookMetadata;
use App\Enums\BookAvailability;
use App\Models\Book;
use App\Models\Publisher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Five real books with real covers, per the demo rules: no lorem ipsum anywhere
 * the client might look.
 *
 * The bibliographic data is hand-curated in books.json rather than fetched,
 * so seeding is deterministic and works offline. Covers are pulled through the
 * same action the Filament resource uses, and simply skipped when there is no
 * network.
 */
class BookSeeder extends Seeder
{
    public function __construct(
        private FetchBookMetadata $fetchMetadata,
        private DownloadBookCover $downloadCover,
    ) {}

    public function run(): void
    {
        foreach ($this->books() as $data) {
            $publisher = Publisher::firstOrCreate(
                ['slug' => Str::slug($data['publisher'])],
                ['name' => $data['publisher']],
            );

            $book = Book::updateOrCreate(
                ['isbn13' => $data['isbn13']],
                [
                    ...collect($data)->except(['publisher', 'contributors'])->all(),
                    'contributors'           => $data['contributors'],
                    'publisher_id'           => $publisher->id,
                    'availability'           => $data['availability'] ?? BookAvailability::Available->value,
                    'country_of_publication' => 'ES',
                    'metadata_source'        => 'manual',
                ],
            );

            $this->attachCover($book);
        }
    }

    /**
     * Covers are fetched at seed time rather than committed as binaries.
     *
     * books.json may name the source directly, which is how the recent Spanish
     * titles get a cover at all: Open Library has none of them. Anything
     * without an explicit URL falls back to the metadata providers, and
     * offline the book simply ends up with no cover.
     *
     * A cover left on the disk by an earlier seed is reused rather than
     * downloaded again, so re-seeding does not lose every cover whose source is
     * unreachable that afternoon.
     */
    private function attachCover(Book $book): void
    {
        if ($book->hasMedia(Book::COVERS_COLLECTION)) {
            return;
        }

        if ($jpeg = $this->coverOnDisk($book->isbn13)) {
            $book->addCoverFromString($jpeg);

            return;
        }

        $sourceUrl = $book->cover_source_url
            ?? ($this->fetchMetadata)($book->isbn13)?->coverSourceUrl;

        $jpeg = ($this->downloadCover)($sourceUrl, $book->isbn13);

        if ($jpeg === null) {
            return;
        }

        $book->update(['cover_source_url' => $sourceUrl]);
        $book->addCoverFromString($jpeg);
    }

    /**
     * A previously downloaded cover for this ISBN, if one is still on the disk.
     */
    private function coverOnDisk(string $isbn13): ?string
    {
        $disk = Storage::disk(config('books.covers.seed_disk'));
        $path = config('books.covers.seed_directory') . "/{$isbn13}." . DownloadBookCover::EXTENSION;

        return $disk->exists($path) ? $disk->get($path) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function books(): array
    {
        $path = database_path('seeders/data/books.json');

        return json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
