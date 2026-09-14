<?php

namespace App\Filament\Actions;

use App\Models\Author;
use App\Models\Book;
use App\Models\Publisher;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Open the record's own page on the web, in a tab of its own.
 *
 * For a book it reads nothing but the record, not `is_active`: a book still
 * being catalogued is a 404 for a reader but visible to a bookseller, which is
 * exactly when they want to look at it. An author is different -- their page is
 * a 404 for everyone while nothing of theirs is on the web -- so for an author
 * the button is simply not there until it would lead somewhere.
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
            ->label(__('panel.actions.view_on_site'))
            ->icon(Heroicon::ArrowTopRightOnSquare)
            ->color('gray')
            ->url(fn(Model $record): string => route($this->routeFor($record), $record))
            ->visible(fn(Model $record): bool => ! $record instanceof Author || $this->hasShelf($record))
            ->openUrlInNewTab();
    }

    private function routeFor(Model $record): string
    {
        return match (true) {
            $record instanceof Book      => 'books.show',
            $record instanceof Author    => 'authors.show',
            $record instanceof Publisher => 'publishers.show',
            default                      => throw new InvalidArgumentException('No page on the site for ' . $record::class),
        };
    }

    private function hasShelf(Author $author): bool
    {
        return Book::query()
            ->active()
            ->whereHas('contributors', fn(Builder $query): Builder => $query->whereBelongsTo($author))
            ->exists();
    }
}
