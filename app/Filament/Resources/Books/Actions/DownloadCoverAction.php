<?php

namespace App\Filament\Resources\Books\Actions;

use App\Actions\Books\DownloadBookCover;
use App\Models\Book;
use Filament\Actions\Action;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * Fetch a cover from a URL the bookseller supplies.
 *
 * The ISBN lookup only produces a cover when a provider happens to have one,
 * and for recent Spanish titles it usually does not. This is the way out: paste
 * the address of the image -- from the publisher, from a distributor -- and it
 * is fetched through the same guarded pipeline, so the allowlist, the size
 * floor and the re-encoding all still apply.
 *
 * On an edit page the cover is attached there and then. On a create page there
 * is no record to attach it to yet, so the URL is recorded and AttachBookCover
 * picks it up after the save.
 */
class DownloadCoverAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'downloadCover';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('books.cover_download.label'))
            ->icon(Heroicon::ArrowDownTray)
            ->modalHeading(__('books.cover_download.heading'))
            ->modalSubmitActionLabel(__('books.cover_download.submit'))
            ->fillForm(fn(Get $get): array => ['url' => $get('cover_source_url')])
            ->schema([
                TextInput::make('url')
                    ->label(__('books.fields.cover_source_url'))
                    ->helperText(__('books.hints.cover_source_url', [
                        'hosts' => implode(', ', (array)config('books.covers.allowed_hosts')),
                    ]))
                    ->url()
                    ->required(),
            ])
            ->action($this->download(...));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function download(
        array $data,
        Set $set,
        ?Book $record,
        SpatieMediaLibraryFileUpload $component,
        DownloadBookCover $downloadCover,
    ): void {
        $url = (string)$data['url'];

        $set('cover_source_url', $url);

        if (! $record instanceof Book) {
            Notification::make()
                ->success()
                ->title(__('books.cover_download.deferred_title'))
                ->body(__('books.cover_download.deferred_body'))
                ->send();

            return;
        }

        $jpeg = $downloadCover($url, $record->isbn13);

        if ($jpeg === null) {
            Notification::make()
                ->danger()
                ->title(__('books.cover_download.failed_title'))
                ->body(__('books.cover_download.failed_body', [
                    'width'  => config('books.covers.min_width'),
                    'height' => config('books.covers.min_height'),
                ]))
                ->send();

            return;
        }

        $record->addCoverFromString($jpeg);
        $record->update(['cover_source_url' => $url]);

        /* Re-read the collection so the new cover shows without a reload. */
        $component->loadStateFromRelationships(true);

        Notification::make()
            ->success()
            ->title(__('books.cover_download.done_title'))
            ->send();
    }
}
