<?php

namespace App\Filament\Resources\Publishers\Tables;

use App\Models\Publisher;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PublishersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')
                    ->label(__('publishers.fields.logo_path'))
                    ->disk(config('books.logos.disk'))
                    ->imageHeight(40)
                    ->sortable(false),

                TextColumn::make('name')
                    ->label(__('publishers.fields.name'))
                    ->description(fn(Publisher $record): ?string => $record->website)
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('books_count')
                    ->label(__('publishers.fields.books_count'))
                    ->counts('books')
                    ->badge()
                    ->alignCenter()
                    ->sortable(false),

                TextColumn::make('slug')
                    ->label(__('publishers.fields.slug'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('publishers.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('books')
                    ->label(__('publishers.filters.with_books'))
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
