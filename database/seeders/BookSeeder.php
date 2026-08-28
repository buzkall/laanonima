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
                    'availability'           => $data['availability'] ?? BookAvailability::Disponible->value,
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
     * A cover already sitting on the disk from an earlier seed is reused, so a
     * migrate:fresh does not lose every cover whose source is unreachable that
     * afternoon.
     */
    private function attachCover(Book $book): void
    {
        if (filled($book->cover_path)) {
            return;
        }

        if ($path = $this->coverOnDisk($book->isbn13)) {
            $book->update(['cover_path' => $path]);

            return;
        }

        $sourceUrl = $book->cover_source_url
            ?? ($this->fetchMetadata)($book->isbn13)?->coverSourceUrl;

        $path = ($this->downloadCover)($sourceUrl, $book->isbn13);

        if ($path === null) {
            return;
        }

        $book->update([
            'cover_path'       => $path,
            'cover_source_url' => $sourceUrl,
        ]);
    }

    /**
     * The path of a previously downloaded cover for this ISBN, if any.
     */
    private function coverOnDisk(string $isbn13): ?string
    {
        $path = config('books.covers.directory') . "/{$isbn13}." . DownloadBookCover::EXTENSION;

        return Storage::disk(config('books.covers.disk'))->exists($path) ? $path : null;
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
