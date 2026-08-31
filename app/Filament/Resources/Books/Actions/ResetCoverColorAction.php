<?php

namespace App\Filament\Resources\Books\Actions;

use App\Actions\Books\ExtractCoverColor;
use App\Models\Book;
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * Hand the cover colour back to the cover.
 *
 * A colour on the record is never written over -- SyncCoverColor only fills an
 * empty column -- so once a book has one, this is the way to take the current
 * cover's colour instead. Emptying the field does the same for the next image
 * that arrives; this is for the image already there.
 *
 * Only the form state is touched, like every other field on the page: nothing
 * is written until the bookseller saves.
 */
class ResetCoverColorAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'resetCoverColor';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('books.cover_color.reset'))
            ->icon(Heroicon::ArrowPath)
            ->action($this->reset(...));
    }

    private function reset(Set $set, ?Book $record, ExtractCoverColor $extractColor): void
    {
        /* Covers uploaded earlier in this same visit are not in the loaded
           relation, exactly as in SyncCoverColor. */
        $cover = $record instanceof Book ? $record->load('media')->cover() : null;

        $set('cover_color', $extractColor($cover));
    }
}
