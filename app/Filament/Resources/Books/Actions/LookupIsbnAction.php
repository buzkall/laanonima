<?php

namespace App\Filament\Resources\Books\Actions;

use App\Actions\Books\FetchBookMetadata;
use App\Models\Publisher;
use App\Support\BookMetadata\BookMetadata;
use App\Support\Isbn as IsbnHelper;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Type an ISBN, press the magnifier, get a filled-in record.
 *
 * A miss is an ordinary outcome, not an error: roughly one Spanish ISBN in six
 * is absent from the free sources, so the notification says "fill it in by
 * hand" and leaves whatever the bookseller already typed alone.
 */
class LookupIsbnAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'lookup';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('books.lookup.label'))
            ->icon(Heroicon::MagnifyingGlass)
            ->action($this->lookup(...));
    }

    private function lookup(Get $get, Set $set, FetchBookMetadata $fetchMetadata): void
    {
        $isbn13 = IsbnHelper::toIsbn13($get('isbn13'));

        if ($isbn13 === null) {
            Notification::make()
                ->warning()
                ->title(__('books.lookup.invalid_title'))
                ->body(__('books.lookup.invalid_body'))
                ->send();

            return;
        }

        $metadata = $fetchMetadata($isbn13);

        if (! $metadata instanceof BookMetadata) {
            Notification::make()
                ->warning()
                ->title(__('books.lookup.not_found_title'))
                ->body(__('books.lookup.not_found_body'))
                ->send();

            return;
        }

        foreach ($metadata->toBookAttributes() as $field => $value) {
            $set($field, $value);
        }

        $set('metadata_synced_at', now());

        if (blank($get('publisher_id')) && filled($metadata->publisherName)) {
            $set('publisher_id', Publisher::firstOrCreate(
                ['slug' => Str::slug($metadata->publisherName)],
                ['name' => $metadata->publisherName],
            )->id);
        }

        /*
         | Say plainly when the sources had no cover. Roughly one Spanish ISBN in
         | six comes back without one, and a bookseller has no other way to tell
         | that apart from a broken download.
         */
        $coverNote = blank($metadata->coverSourceUrl)
            ? ' ' . __('books.lookup.found_without_cover')
            : '';

        Notification::make()
            ->success()
            ->title(__('books.lookup.found_title'))
            ->body(__('books.lookup.found_body', ['title' => $metadata->title ?? $isbn13]) . $coverNote)
            ->send();
    }
}
