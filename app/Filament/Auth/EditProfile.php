<?php

namespace App\Filament\Auth;

use App\Filament\Actions\GeneratePasswordAction;
use App\Support\AccountPassword;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

/**
 * The account page a signed-in user gets of their own record.
 *
 * Filament's own form covers the name, the address and the password. The
 * telephone is added because the shop reads it off `users` when it needs to
 * call a reader back about a request, so this is the one place a reader can
 * correct a number they mistyped.
 *
 * The role is deliberately absent: it decides which panel its owner may enter,
 * and is the admin panel's to set.
 */
class EditProfile extends BaseEditProfile
{
    /**
     * Filament lays the profile out as one inline-labeled column. This is the
     * users resource's section instead, so a reader and an administrator are
     * looking at the same form.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('user.sections.account'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        $this->getPhoneFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
            ]);
    }

    /**
     * Labels sit above their field, not beside it: two columns of inline
     * labels leave the inputs too narrow to read a password back from.
     */
    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm($schema)->inlineLabel(false);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label(__('user.fields.phone'))
            ->tel()
            ->maxLength(60);
    }

    /**
     * The same password box the users resource has, minus what only an
     * administrator needs.
     *
     * A reader must be able to type back a password the panel generated for
     * them, so the rule is `AccountPassword` rather than Filament's default,
     * and the generator is offered here too. What is kept from Filament's own
     * component is how the value reaches the record: the profile page writes
     * the hash it saves into the session (`password_hash_*`), so the state has
     * to be hashed here rather than left to the `hashed` cast, or the next
     * request would sign the reader out.
     *
     * An empty box is not a request to blank the password, so nothing is
     * dehydrated unless something was typed. `columnStart(1)` opens a row for
     * it, so the two password boxes sit side by side as they do in the users
     * resource rather than one of them pairing off with the telephone.
     */
    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('user.fields.password'))
            ->validationAttribute(__('user.fields.password'))
            ->password()
            ->revealable()
            ->rules([AccountPassword::rule()])
            ->same('passwordConfirmation')
            ->showAllValidationMessages()
            ->autocomplete('new-password')
            ->maxLength(255)
            ->dehydrated(fn(#[SensitiveParameter] ?string $state): bool => filled($state))
            ->dehydrateStateUsing(fn(#[SensitiveParameter] string $state): string => Hash::make($state))
            ->live(onBlur: true)
            ->helperText(__('user.helpers.password'))
            ->hintAction(GeneratePasswordAction::make()->confirmationField('passwordConfirmation'))
            ->extraAlpineAttributes(GeneratePasswordAction::revealAndCopyAttributes())
            ->columnStart(1);
    }

    /**
     * Always on the page, required only once a password is being set.
     *
     * Filament hides the box until the password field reports a value, which
     * costs a round trip: Tab carries you past the spot the box is about to
     * appear in. It stays put instead, as in the users resource, and
     * `required()` below is the server's word on whether a confirmation was
     * owed. It is never saved -- there is no such column, the box is here for
     * the `same()` rule on the field above.
     */
    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('user.fields.password_confirmation'))
            ->validationAttribute(__('user.fields.password_confirmation'))
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            ->required(fn(Get $get): bool => filled($get('password')))
            ->maxLength(255)
            ->dehydrated(false)
            ->helperText(__('user.helpers.password_requirements'));
    }
}
