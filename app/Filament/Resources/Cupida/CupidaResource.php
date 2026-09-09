<?php

namespace App\Filament\Resources\Cupida;

use App\Filament\Resources\Cupida\Pages\ListCupida;
use App\Filament\Resources\Cupida\Tables\CupidaTable;
use App\Models\CupidaRecommendation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A log, not a catalog: one page, no create, no edit, no delete.
 *
 * A recommendation is something that already happened, so there is nothing here
 * to author. What the shop does with it is read it -- which questions people
 * answer yes to, which books keep coming up, and whether the pitches are any
 * good -- and, from the header action, change what La Cupida is told to say.
 *
 * `canCreate()` is Filament's own question and is answered here;
 * CupidaRecommendationPolicy answers the rest, so a row cannot be edited or
 * deleted through a route either.
 */
class CupidaResource extends Resource
{
    protected static ?string $model = CupidaRecommendation::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;
    protected static ?string $recordTitleAttribute = 'title';

    /* Pinned, because the name of this resource is a name rather than a noun:
       left to Filament it pluralises to /admin/cupida/cupidas. */
    protected static ?string $slug = 'cupida';
    protected static ?int $navigationSort = 40;

    /**
     * The page is called La Cupida; a row in it is a recommendation.
     *
     * Filament builds both out of the model label by default, which forces one
     * name to do both jobs -- and "2 La Cupida seleccionadas" is not a sentence.
     * So the heading and the breadcrumb are set from `resource.title` and the
     * model labels stay what a single row actually is.
     */
    public static function getBreadcrumb(): string
    {
        return __('cupida.admin.resource.title');
    }

    public static function getModelLabel(): string
    {
        return __('cupida.admin.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cupida.admin.resource.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('cupida.admin.resource.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('cupida.admin.resource.navigation_group');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return CupidaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCupida::route('/'),
        ];
    }
}
