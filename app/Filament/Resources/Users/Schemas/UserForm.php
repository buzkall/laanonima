<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Filament\Actions\GeneratePasswordAction;
use App\Models\User;
use App\Support\AccountPassword;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('user.sections.account'))
                    ->afterHeader([self::emailVerificationBadge()])
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

                        TextInput::make('phone')
                            ->label(__('user.fields.phone'))
                            ->tel()
                            ->maxLength(60),

                        ToggleButtons::make('role')
                            ->label(__('user.fields.role'))
                            ->options(UserRole::class)
                            ->default(UserRole::Client)
                            ->required()
                            ->grouped(),

                        self::passwordField(),

                        self::passwordConfirmationField(),
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
            ->rules([AccountPassword::rule()])
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
            ->hintAction(GeneratePasswordAction::make())
            ->extraAlpineAttributes(GeneratePasswordAction::revealAndCopyAttributes());
    }

    /**
     * Owed once a password is being set, and never saved: the column does not
     * exist, the field is here for the `confirmed()` rule above.
     *
     * Always on the page, and only `required()` is conditional. It was hidden
     * until a password had been typed -- first behind a `visible()` closure,
     * then a `visibleJs()` one -- and neither was worth it: a box that appears
     * under the cursor moves the rest of the form out from under somebody
     * tabbing through it. Do not put the condition back on visibility. The
     * server's word on whether a confirmation was owed is `required()`, which
     * is also what keeps `fillForm()` reaching this field in tests.
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
     * Whether this address has been confirmed, read rather than set.
     *
     * It sits in the section header, away from the fields, because it is not
     * something an administrator fills in: verification happens when the reader
     * follows the link we sent them.
     */
    private static function emailVerificationBadge(): TextEntry
    {
        return TextEntry::make('email_verified_at')
            ->hiddenLabel()
            ->badge()
            ->state(function(?User $record): string {
                $verifiedAt = $record?->email_verified_at;

                if ($verifiedAt === null) {
                    return __('user.placeholders.not_verified');
                }

                return __('user.badges.verified', ['date' => $verifiedAt->translatedFormat('d/m/Y')]);
            })
            ->color(fn(?User $record): string => $record?->email_verified_at === null ? 'gray' : 'success')
            ->icon(fn(?User $record): Heroicon => $record?->email_verified_at === null ? Heroicon::OutlinedClock : Heroicon::OutlinedCheckBadge)
            ->visibleOn('edit');
    }
}
