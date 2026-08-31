<?php

namespace App\Filament\Resources\Books\Pages;

use App\Actions\Books\AttachBookCover;
use App\Enums\BookCoverOutcome;
use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditBook extends EditRecord
{
    protected static string $resource = BookResource::class;

    /**
     * The page reads is_active, not the panel: a book still being catalogued is
     * a 404 for a reader but visible to an administrator, which is exactly when
     * the bookseller wants to look at it.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOnSite')
                ->label(__('books.actions.view_on_site'))
                ->icon(Heroicon::ArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn(Book $record): string => route('books.show', $record))
                ->openUrlInNewTab(),

            DeleteAction::make(),
        ];
    }

    /**
     * Fetch a cover only when this save is the one that named a new source,
     * which is to say when the bookseller just ran the ISBN lookup.
     *
     * Attaching on every save would resurrect an image the bookseller had
     * deliberately deleted a moment earlier.
     */
    protected function afterSave(): void
    {
        /** @var Book $book */
        $book = $this->record;

        if ($book->wasChanged('cover_source_url')
            && app(AttachBookCover::class)($book) === BookCoverOutcome::Failed) {
            Notification::make()
                ->warning()
                ->title(__('books.cover_download.failed_title'))
                ->body(__('books.cover_download.failed_after_save'))
                ->persistent()
                ->send();
        }

        /*
         | Always, not just on the download path: cover_color is derived from
         | the media, which is attached after the form was filled, so uploading
         | an image would otherwise leave the swatch showing the old colour
         | until the page was reloaded.
         */
        $this->refreshFormData(['cover_color']);
    }
}
