<?php

namespace App\Filament\Resources\Books\Tables;

use App\Enums\BookAvailability;
use App\Enums\BookBinding;
use App\Filament\Resources\Authors\RelationManagers\BooksRelationManager as AuthorBooksRelationManager;
use App\Filament\Resources\Books\Actions\ViewOnSiteAction;
use App\Filament\Resources\Publishers\RelationManagers\BooksRelationManager;
use App\Models\Author;
use App\Models\Book;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
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
            ->modifyQueryUsing(fn(Builder $query, HasTable $livewire): Builder => $query
                ->with('publisher')
                ->when(
                    $livewire instanceof AuthorBooksRelationManager,
                    fn(Builder $query): Builder => $query->with('contributors'),
                ))
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
                    ->description(fn(Book $record, HasTable $livewire): ?string => $livewire instanceof BooksRelationManager
                        ? null
                        : $record->publisher?->name)
                    ->searchable(query: fn(Builder $query, string $search): Builder => $query
                        ->where('authors_line', 'like', "%{$search}%")
                        ->orWhereRelation('publisher', 'name', 'like', "%{$search}%"))
                    ->sortable()
                    ->toggleable(),

                /* Inside an author's tab the book's own author is not the
                   reason it is listed -- this says what that person did. */
                TextColumn::make('contribution')
                    ->label(__('books.fields.contributor_role'))
                    ->badge()
                    ->state(function(Book $record, HasTable $livewire): array {
                        $owner = $livewire instanceof AuthorBooksRelationManager
                            ? $livewire->getOwnerRecord()
                            : null;

                        return $owner instanceof Author ? $record->rolesFor($owner) : [];
                    })
                    ->visible(fn(HasTable $livewire): bool => $livewire instanceof AuthorBooksRelationManager)
                    ->sortable(false),

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
                SelectFilter::make('authors')
                    ->label(__('books.fields.authors_line'))
                    ->relationship('authors', 'name')
                    ->searchable()
                    ->preload()
                    ->hiddenOn(AuthorBooksRelationManager::class),

                SelectFilter::make('publisher')
                    ->label(__('books.fields.publisher_id'))
                    ->relationship('publisher', 'name')
                    ->searchable()
                    ->preload()
                    ->hiddenOn(BooksRelationManager::class),

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
            /* Inside an author's or a publisher's tab one filter is hidden, so
               the five that remain fill the row instead of leaving a gap. */
            ->filtersFormColumns(fn(HasTable $livewire): int => $livewire instanceof RelationManager ? 5 : 6)
            ->recordActions([
                ViewOnSiteAction::make()->iconButton(),
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
