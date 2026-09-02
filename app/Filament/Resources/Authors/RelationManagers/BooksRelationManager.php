<?php

namespace App\Filament\Resources\Authors\RelationManagers;

use App\Filament\Resources\Books\BookResource;
use App\Models\Author;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;

/**
 * Every book this person had a hand in, in any role, listed with the books resource's own table
 * through `$relatedResource`, so a row here cannot drift from a row there and
 * the edit action opens the full book form rather than a modal.
 *
 * No create action, for the same reason as on the publisher: a new book needs
 * the whole form, and Filament cannot seed the author into it from here.
 */
class BooksRelationManager extends RelationManager
{
    protected static string $relationship = 'books';
    protected static ?string $relatedResource = BookResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('authors.relations.books');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord instanceof Author ? (string)$ownerRecord->books()->count() : null;
    }
}
