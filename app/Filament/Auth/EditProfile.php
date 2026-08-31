<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

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
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPhoneFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label(__('user.fields.phone'))
            ->tel()
            ->maxLength(60);
    }
}
