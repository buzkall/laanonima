<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class UserForm
{
    private const PASSWORD_LENGTH = 12;

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

                        self::passwordField(),

                        self::passwordConfirmationField(),

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

    /**
     * The password is only written when something was typed.
     *
     * `User::casts()` declares `password => hashed`, so the plain state is
     * hashed by Eloquent on save and this field must not hash it again. On edit
     * an empty field is not a request to blank the password, so `saved()` keeps
     * it out of the update entirely.
     */
    private static function passwordField(): TextInput
    {
        return TextInput::make('password')
            ->label(__('user.fields.password'))
            ->password()
            ->revealable()
            ->confirmed()
            ->rules([Password::min(self::PASSWORD_LENGTH)->letters()->mixedCase()->numbers()])
            ->required(fn(string $operation): bool => $operation === 'create')
            ->saved(fn(?string $state): bool => filled($state))
            ->maxLength(255)
            ->live(onBlur: true)
            ->helperText(function(string $operation): ?string {
                if ($operation !== 'edit') {
                    return null;
                }

                return __('user.helpers.password');
            })
            ->hintAction(self::generatePasswordAction())
            ->extraAlpineAttributes([
                'x-on:reveal-password.window'   => 'isPasswordRevealed = true',
                'x-on:copy-to-clipboard.window' => 'navigator.clipboard.writeText($event.detail.text)',
            ]);
    }

    /**
     * Only demanded once a password is being set, and never saved: the column
     * does not exist, the field is here for the `confirmed()` rule above.
     */
    private static function passwordConfirmationField(): TextInput
    {
        return TextInput::make('password_confirmation')
            ->label(__('user.fields.password_confirmation'))
            ->password()
            ->revealable()
            ->required(fn(Get $get): bool => filled($get('password')))
            ->maxLength(255)
            ->saved(false)
            ->helperText(__('user.helpers.password_requirements'));
    }

    /**
     * Fills both fields with a password nobody has to invent.
     *
     * The generated value is revealed and copied to the clipboard in the same
     * click, because a password that is neither visible nor copied is a
     * password the administrator cannot pass on to the user.
     */
    private static function generatePasswordAction(): Action
    {
        return Action::make('generatePassword')
            ->label(__('user.actions.generate_password'))
            ->icon(Heroicon::OutlinedKey)
            ->color('info')
            ->badge()
            ->action(function(Set $set, Component $livewire): void {
                $password = self::generatePassword();

                $set('password', $password);
                $set('password_confirmation', $password);

                $livewire->dispatch('reveal-password');
                $livewire->dispatch('copy-to-clipboard', text: $password);

                Notification::make()
                    ->success()
                    ->title(__('user.actions.password_generated_title'))
                    ->body(__('user.actions.password_generated_body'))
                    ->send();
            });
    }

    /**
     * `Str::password()` guarantees length but not case mix, so a run of it can
     * lose to the `mixedCase()` rule roughly once in every few hundred clicks.
     * Rerolling is cheaper than explaining that error to the administrator.
     */
    private static function generatePassword(): string
    {
        do {
            $password = str()->password(16);
        } while (! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password) || ! preg_match('/\d/', $password));

        return $password;
    }
}
