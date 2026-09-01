<?php

namespace App\Filament\Resources\Publishers\RelationManagers;

use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;

/**
 * The catalogue a publisher has on the shelves, listed with the same columns as
 * the books resource: `$relatedResource` hands the table over to
 * `BookResource::configureTable()`, so a row here cannot drift from a row there,
 * and the edit action opens the full book form instead of a modal that would
 * have to duplicate the ISBN lookup and the cover pipeline.
 *
 * There is deliberately no create action: a new book needs the publisher picked
 * anyway, and Filament cannot seed it into the resource's create page from here.
 */
class BooksRelationManager extends RelationManager
{
    protected static string $relationship = 'books';
    protected static ?string $relatedResource = BookResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('publishers.relations.books');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string)Book::whereBelongsTo($ownerRecord)->count();
    }
}
