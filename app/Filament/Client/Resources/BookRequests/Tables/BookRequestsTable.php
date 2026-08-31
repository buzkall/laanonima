<?php

namespace App\Filament\Client\Resources\BookRequests\Tables;

use App\Filament\Client\Resources\BookRequests\Actions\WithdrawBookRequestAction;
use App\Models\BookRequest;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('book_requests.fields.title'))
                    ->description(fn(BookRequest $record): ?string => $record->author)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('status')
                    ->label(__('book_requests.fields.status'))
                    ->badge(),

                TextColumn::make('created_at')
                    ->label(__('book_requests.fields.created_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('book_requests.client.empty'))
            ->emptyStateDescription(__('book_requests.client.empty_hint'))
            ->recordActions([
                WithdrawBookRequestAction::make(),
            ]);
    }
}
