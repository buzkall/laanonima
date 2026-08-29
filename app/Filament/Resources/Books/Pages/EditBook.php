<?php

namespace App\Filament\Resources\Books\Pages;

use App\Actions\Books\AttachBookCover;
use App\Enums\BookCoverOutcome;
use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBook extends EditRecord
{
    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

        if (! $book->wasChanged('cover_source_url')) {
            return;
        }

        if (app(AttachBookCover::class)($book) === BookCoverOutcome::Failed) {
            Notification::make()
                ->warning()
                ->title(__('books.cover_download.failed_title'))
                ->body(__('books.cover_download.failed_after_save'))
                ->persistent()
                ->send();
        }
    }
}
