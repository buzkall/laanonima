<?php

namespace App\Filament\Resources\Books\Pages;

use App\Actions\Books\AttachBookCover;
use App\Enums\BookCoverOutcome;
use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBook extends CreateRecord
{
    protected static string $resource = BookResource::class;

    /**
     * The ISBN lookup could only note where the cover lives; now that the book
     * has an id, fetch it. Runs after Filament has saved the form's own
     * uploads, so a bookseller who attached their own image wins.
     *
     * A refusal is said out loud. This used to fail in silence, which is
     * indistinguishable from the feature being broken.
     */
    protected function afterCreate(): void
    {
        /** @var Book $book */
        $book = $this->record;

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
