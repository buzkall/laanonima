<?php

namespace App\Filament\Resources\Books\Tables;

use App\Enums\BookAvailability;
use App\Enums\BookBinding;
use App\Models\Book;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BooksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with('publisher'))
            ->columns([
                SpatieMediaLibraryImageColumn::make('cover')
                    ->label(__('books.fields.cover'))
                    ->collection(Book::COVERS_COLLECTION)
                    ->conversion('thumb')
                    ->limit(1)
                    ->imageHeight(56)
                    ->sortable(false),

                TextColumn::make('title')
                    ->label(__('books.fields.title'))
                    ->description(fn(Book $record): string => $record->isbn13)
                    ->searchable(['title', 'isbn13'])
                    ->sortable()
                    ->wrap(),

                TextColumn::make('authors_line')
                    ->label(__('books.fields.authors_line'))
                    ->description(fn(Book $record): ?string => $record->publisher?->name)
                    ->searchable(query: fn(Builder $query, string $search): Builder => $query
                        ->where('authors_line', 'like', "%{$search}%")
                        ->orWhereRelation('publisher', 'name', 'like', "%{$search}%"))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('stock')
                    ->label(__('books.fields.stock'))
                    ->numeric()
                    ->badge()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('price_cents')
                    ->label(__('books.fields.price_cents'))
                    ->money('EUR', divideBy: 100, locale: 'es')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('availability')
                    ->label(__('books.fields.availability'))
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('binding')
                    ->label(__('books.fields.binding'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('published_year')
                    ->label(__('books.fields.published_year'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('books.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('publisher')
                    ->label(__('books.fields.publisher_id'))
                    ->relationship('publisher', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('availability')
                    ->label(__('books.fields.availability'))
                    ->options(BookAvailability::class),

                SelectFilter::make('binding')
                    ->label(__('books.fields.binding'))
                    ->options(BookBinding::class),

                TernaryFilter::make('is_featured')
                    ->label(__('books.filters.featured')),

                TernaryFilter::make('is_active')
                    ->label(__('books.filters.active')),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->recordActions([
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
