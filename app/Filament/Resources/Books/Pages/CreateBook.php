<?php

namespace App\Filament\Resources\Books\Pages;

use App\Actions\Books\AttachBookCover;
use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use Filament\Resources\Pages\CreateRecord;

class CreateBook extends CreateRecord
{
    protected static string $resource = BookResource::class;

    /**
     * The ISBN lookup could only note where the cover lives; now that the book
     * has an id, fetch it. Runs after Filament has saved the form's own
     * uploads, so a bookseller who attached their own image wins.
     */
    protected function afterCreate(): void
    {
        /** @var Book $book */
        $book = $this->record;

        app(AttachBookCover::class)($book);
    }
}
