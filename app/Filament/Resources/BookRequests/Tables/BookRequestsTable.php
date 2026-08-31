<?php

namespace App\Filament\Resources\BookRequests\Tables;

use App\Enums\BookRequestStatus;
use App\Models\BookRequest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('book_requests.fields.created_at'))
                    ->dateTime()
                    ->since()
                    ->tooltip(fn(BookRequest $record): string => $record->created_at?->translatedFormat('d/m/Y H:i') ?? '')
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('book_requests.fields.title'))
                    ->description(fn(BookRequest $record): ?string => $record->author)
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('user.name')
                    ->label(__('book_requests.fields.user_id'))
                    ->description(fn(BookRequest $record): string => $record->user->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('book_requests.fields.status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('book.title')
                    ->label(__('book_requests.fields.book_id'))
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('isbn')
                    ->label(__('book_requests.fields.isbn'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('publisher')
                    ->label(__('book_requests.fields.publisher'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.phone')
                    ->label(__('book_requests.fields.phone'))
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('book_requests.fields.status'))
                    ->options(BookRequestStatus::class)
                    ->multiple(),

                TernaryFilter::make('open')
                    ->label(__('book_requests.filters.mine'))
                    ->queries(
                        true: fn(Builder $query): Builder => $query->whereIn('status', BookRequestStatus::open()),
                        false: fn(Builder $query): Builder => $query->whereNotIn('status', BookRequestStatus::open()),
                        blank: fn(Builder $query): Builder => $query,
                    ),

                TernaryFilter::make('book_id')
                    ->label(__('book_requests.filters.in_catalogue'))
                    ->queries(
                        true: fn(Builder $query): Builder => $query->whereNotNull('book_id'),
                        false: fn(Builder $query): Builder => $query->whereNull('book_id'),
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
