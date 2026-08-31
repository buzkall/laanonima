<?php

namespace App\Filament\Client\Resources\BookRequests;

use App\Filament\Client\Resources\BookRequests\Pages\ListBookRequests;
use App\Filament\Client\Resources\BookRequests\Tables\BookRequestsTable;
use App\Models\BookRequest;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a reader sees of their own book requests: a list and nothing else.
 *
 * The same model the shop works from, scoped down to the signed-in reader. It
 * has one page and no form on purpose -- a request is a message to the shop,
 * and letting the sender rewrite it after the fact would leave the bookseller
 * chasing a title that has quietly changed. The only thing they may do is call
 * it off (`BookRequestPolicy::withdraw`).
 */
class BookRequestResource extends Resource
{
    protected static ?string $model = BookRequest::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;
    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('book_requests.client.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('book_requests.client.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('book_requests.client.navigation_label');
    }

    /**
     * A reader's own requests and no one else's.
     *
     * The scope is here rather than on the table, so a record reached by URL is
     * out of reach too, not merely absent from the listing. With nobody signed
     * in there is nothing to show: the query is emptied rather than left whole.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user instanceof User
            ? $query->whereBelongsTo($user)
            : $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        return BookRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookRequests::route('/'),
        ];
    }
}
