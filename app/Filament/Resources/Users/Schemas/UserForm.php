<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('user.sections.account'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('user.fields.name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('user.fields.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('password')
                            ->label(__('user.fields.password'))
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->saved(fn(?string $state): bool => filled($state))
                            ->helperText(function(string $operation): ?string {
                                if ($operation !== 'edit') {
                                    return null;
                                }

                                return __('user.helpers.password');
                            }),

                        ToggleButtons::make('role')
                            ->label(__('user.fields.role'))
                            ->options(UserRole::class)
                            ->default(UserRole::Client)
                            ->required()
                            ->grouped(),

                        DateTimePicker::make('email_verified_at')
                            ->label(__('user.fields.email_verified_at'))
                            ->seconds(false),
                    ]),
            ]);
    }
}
