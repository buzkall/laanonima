<?php

namespace App\Filament\Resources\Authors\Tables;

use App\Models\Author;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuthorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('authors.fields.name'))
                    ->description(fn(Author $record): ?string => $record->bioExcerpt(90))
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('books_count')
                    ->label(__('authors.fields.books_count'))
                    ->counts('books')
                    ->badge()
                    ->alignCenter()
                    ->sortable(false),

                TextColumn::make('slug')
                    ->label(__('authors.fields.slug'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('authors.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('books')
                    ->label(__('authors.filters.with_books'))
                    ->queries(
                        true: fn(Builder $query): Builder => $query->has('books'),
                        false: fn(Builder $query): Builder => $query->doesntHave('books'),
                        blank: fn(Builder $query): Builder => $query,
                    ),
            ], layout: FiltersLayout::AboveContent)
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
