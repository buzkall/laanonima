<?php

namespace App\Filament\Resources\Books\Actions;

use App\Models\Book;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Open the book's own page on the web, in a tab of its own.
 *
 * It reads nothing but the record, not `is_active`: a book still being
 * catalogued is a 404 for a reader but visible to a bookseller, which is
 * exactly when they want to look at it.
 */
class ViewOnSiteAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'viewOnSite';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('books.actions.view_on_site'))
            ->icon(Heroicon::ArrowTopRightOnSquare)
            ->color('gray')
            ->url(fn(Book $record): string => route('books.show', $record))
            ->openUrlInNewTab();
    }
}
