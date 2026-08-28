<?php

namespace App\Filament\Resources\Books;

use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Filament\Resources\Books\Schemas\BookForm;
use App\Filament\Resources\Books\Tables\BooksTable;
use App\Models\Book;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BookResource extends Resource
{
    protected static ?string $model = Book::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('books.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('books.resource.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('books.resource.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('books.resource.navigation_group');
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'subtitle', 'authors_line', 'isbn13'];
    }

    public static function form(Schema $schema): Schema
    {
        return BookForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BooksTable::configure($table);
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
            'index'  => ListBooks::route('/'),
            'create' => CreateBook::route('/create'),
            'edit'   => EditBook::route('/{record}/edit'),
        ];
    }
}
