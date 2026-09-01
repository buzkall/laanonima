<?php

namespace App\Filament\Resources\Publishers;

use App\Filament\Resources\Publishers\Pages\CreatePublisher;
use App\Filament\Resources\Publishers\Pages\EditPublisher;
use App\Filament\Resources\Publishers\Pages\ListPublishers;
use App\Filament\Resources\Publishers\RelationManagers\BooksRelationManager;
use App\Filament\Resources\Publishers\Schemas\PublisherForm;
use App\Filament\Resources\Publishers\Tables\PublishersTable;
use App\Models\Publisher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PublisherResource extends Resource
{
    protected static ?string $model = Publisher::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('publishers.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('publishers.resource.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('publishers.resource.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('publishers.resource.navigation_group');
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
        return PublisherForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublishersTable::configure($table);
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
            'index'  => ListPublishers::route('/'),
            'create' => CreatePublisher::route('/create'),
            'edit'   => EditPublisher::route('/{record}/edit'),
        ];
    }
}
