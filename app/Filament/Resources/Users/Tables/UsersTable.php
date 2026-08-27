<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Arzcode\FilamentMagicLogin\Actions\SendMagicLinkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('user.fields.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('user.fields.email'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('role')
                    ->label(__('user.fields.role'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('email_verified_at')
                    ->label(__('user.fields.email_verified_at'))
                    ->dateTime()
                    ->placeholder(__('user.placeholders.not_verified'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('user.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('user.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->label(__('user.filters.role'))
                    ->options(UserRole::class),

                TernaryFilter::make('email_verified_at')
                    ->label(__('user.filters.email_verification.label'))
                    ->nullable()
                    ->placeholder(__('user.filters.email_verification.all'))
                    ->trueLabel(__('user.filters.email_verification.verified'))
                    ->falseLabel(__('user.filters.email_verification.unverified')),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                SendMagicLinkAction::make()
                    ->anyPanel(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
