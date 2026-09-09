<?php

namespace App\Filament\Actions;

use App\Support\AccountPassword;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Fills both password boxes with a password nobody has to invent.
 *
 * The generated value is revealed and copied to the clipboard in the same
 * click, because a password that is neither visible nor copied is a password
 * its owner cannot write down or pass on. Revealing and copying are the
 * browser's work, so the action asks for them by event: pair it with
 * `revealAndCopyAttributes()` on the password field itself.
 *
 * The confirmation box is named differently on either side -- the users
 * resource follows the `confirmed()` rule's `password_confirmation`, Filament's
 * profile page its own `passwordConfirmation` -- hence `confirmationField()`.
 */
class GeneratePasswordAction extends Action
{
    private string $confirmationField = 'password_confirmation';

    public static function getDefaultName(): ?string
    {
        return 'generatePassword';
    }

    /**
     * The state path of the confirmation box to fill alongside the password.
     */
    public function confirmationField(string $statePath): static
    {
        $this->confirmationField = $statePath;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public static function revealAndCopyAttributes(): array
    {
        return [
            'x-on:reveal-password.window'   => 'isPasswordRevealed = true',
            'x-on:copy-to-clipboard.window' => 'navigator.clipboard.writeText($event.detail.text)',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('user.actions.generate_password'))
            ->icon(Heroicon::OutlinedKey)
            ->color('info')
            ->badge()
            ->action($this->generate(...));
    }

    private function generate(Set $set, Component $livewire): void
    {
        $password = AccountPassword::generate();

        $set('password', $password);
        $set($this->confirmationField, $password);

        $livewire->dispatch('reveal-password');
        $livewire->dispatch('copy-to-clipboard', text: $password);

        Notification::make()
            ->success()
            ->title(__('user.actions.password_generated_title'))
            ->body(__('user.actions.password_generated_body'))
            ->send();
    }
}
