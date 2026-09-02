<?php

namespace App\Filament\Resources\Authors;

use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Authors\RelationManagers\BooksRelationManager;
use App\Filament\Resources\Authors\Schemas\AuthorForm;
use App\Filament\Resources\Authors\Tables\AuthorsTable;
use App\Models\Author;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencil;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 15;

    public static function getModelLabel(): string
    {
        return __('authors.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('authors.resource.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('authors.resource.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('authors.resource.navigation_group');
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function form(Schema $schema): Schema
    {
        return AuthorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuthorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BooksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListAuthors::route('/'),
            'create' => CreateAuthor::route('/create'),
            'edit'   => EditAuthor::route('/{record}/edit'),
        ];
    }
}
