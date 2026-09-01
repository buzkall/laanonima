<?php

namespace App\Filament\Resources\BookRequests;

use App\Filament\Resources\BookRequests\Pages\CreateBookRequest;
use App\Filament\Resources\BookRequests\Pages\EditBookRequest;
use App\Filament\Resources\BookRequests\Pages\ListBookRequests;
use App\Filament\Resources\BookRequests\Schemas\BookRequestForm;
use App\Filament\Resources\BookRequests\Tables\BookRequestsTable;
use App\Models\BookRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BookRequestResource extends Resource
{
    protected static ?string $model = BookRequest::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?int $navigationSort = 30;
    protected static bool $hasTitleCaseModelLabel = false;

    public static function getModelLabel(): string
    {
        return __('book_requests.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('book_requests.resource.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('book_requests.resource.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('book_requests.resource.navigation_group');
    }

    /**
     * How many requests nobody has dealt with yet, on the sidebar. This is the
     * one resource the shop has to look at rather than search: a reader is
     * waiting at the other end of every pending row.
     */
    public static function getNavigationBadge(): ?string
    {
        $open = BookRequest::query()->open()->count();

        return $open > 0 ? (string)$open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'author', 'name', 'email', 'isbn'];
    }

    public static function form(Schema $schema): Schema
    {
        return BookRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListBookRequests::route('/'),
            'create' => CreateBookRequest::route('/create'),
            'edit'   => EditBookRequest::route('/{record}/edit'),
        ];
    }
}
