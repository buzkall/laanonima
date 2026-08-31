<?php

namespace App\Filament\Auth;

use App\Enums\UserRole;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use SensitiveParameter;

/**
 * Signing up as a reader.
 *
 * Only the client panel offers this page, and whoever comes through it is a
 * reader: the role is written here rather than left to the column default, so a
 * `role` arriving from anywhere else cannot decide it. Nothing on the form can
 * choose a role -- an administrator is made by an administrator, in the admin
 * panel's users resource.
 *
 * The telephone is asked for once, and optional: it is what lets the shop call
 * back about a request. A reader who leaves it empty is asked again by the book
 * request form, which is where it actually matters.
 */
class Register extends BaseRegister
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
            ]);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label(__('user.fields.phone'))
            ->tel()
            ->maxLength(60);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeRegister(#[SensitiveParameter] array $data): array
    {
        $data['role'] = UserRole::Client;

        return $data;
    }
}
