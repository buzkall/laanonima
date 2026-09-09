<?php

namespace App\Filament\Actions;

use App\Settings\CupidaSettings;
use App\Support\Cupida\CupidaBudget;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * What the shop put on the account, so we can count down from it.
 *
 * It has to be typed because there is nothing to read: Anthropic publishes no
 * balance, and its reports say what was spent rather than what is left. So the
 * countdown is this number minus what the rows have cost since the date beside
 * it, which is exact while nothing else spends the key.
 *
 * The modal shows the sum as well as the field, because a balance entered three
 * months ago and a figure that has not moved since look identical until you can
 * see what has come off it.
 *
 * Who gets told when it runs down is not asked here: it is `site.admin_email`,
 * which is whoever looks after the site rather than whoever tops the account
 * up, and it changes with the environment and not with a modal.
 */
class EditCupidaCreditAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'editCupidaCredit';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('cupida.admin.credit.action'))
            ->icon(Heroicon::OutlinedBanknotes)
            /* Gray: this is housekeeping, not something the shop is being asked
               to act on. The badge beside it goes red when it is, which is the
               only part of the button that should ever ask for attention. */
            ->color('gray')
            /* What the badge is styled through: a dollar figure needs more room
               than the count Filament drew the badge for, and reads better off
               the corner away from the next button. Both live in the admin
               theme, hung on this class so no other badge moves with them. */
            ->extraAttributes(['class' => 'fi-ac-cupida-credit'])
            ->badge(fn(): ?string => $this->budget()->label())
            ->badgeColor(fn(): string => $this->budget()->runningLow() ? 'danger' : 'gray')
            ->modalHeading(__('cupida.admin.credit.heading'))
            ->modalDescription(__('cupida.admin.credit.description'))
            ->modalSubmitActionLabel(__('cupida.admin.credit.save'))
            ->modalWidth('lg')
            ->fillForm(fn(): array => [
                'credit_balance'      => app(CupidaSettings::class)->credit_balance,
                'credit_topped_up_at' => app(CupidaSettings::class)->credit_topped_up_at,
            ])
            ->schema([
                TextInput::make('credit_balance')
                    ->label(__('cupida.admin.credit.balance'))
                    ->helperText(__('cupida.admin.credit.balance_hint'))
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$'),

                /* Left blank it is filled in below rather than saved empty:
                   the countdown is the balance minus the rows since this date,
                   and a balance with no date beside it has nothing to count
                   from. */
                DatePicker::make('credit_topped_up_at')
                    ->label(__('cupida.admin.credit.topped_up_at'))
                    ->native(false)
                    ->maxDate(now()),

                TextEntry::make('spent')
                    ->label(__('cupida.admin.credit.spent'))
                    ->state(fn(): string => '$' . number_format($this->budget()->spent(), 4)),

                TextEntry::make('remaining')
                    ->label(__('cupida.admin.credit.remaining'))
                    ->state(fn(): string => $this->budget()->label() ?? __('cupida.admin.credit.unknown'))
                    ->color(fn(): string => $this->budget()->runningLow() ? 'danger' : 'success'),
            ])
            ->action(function(array $data): void {
                $settings = app(CupidaSettings::class);

                $settings->credit_balance = filled($data['credit_balance'] ?? null)
                    ? (float)$data['credit_balance']
                    : null;

                /* Today when a balance was entered without one. The two are one
                   fact -- what was put on, and when -- and CupidaBudget cannot
                   count the first without the second: saving a figure with a
                   blank date leaves a balance nothing is ever taken off. Blank
                   with no balance stays blank; there is nothing to date. */
                $settings->credit_topped_up_at = match (true) {
                    filled($data['credit_topped_up_at'] ?? null) => now()->parse($data['credit_topped_up_at']),
                    $settings->credit_balance !== null           => now()->startOfDay(),
                    default                                      => null,
                };

                $settings->save();

                /* A top-up is the shop saying the problem is dealt with, so the
                   warning already sent stops being true. Without this the next
                   one waits out a throttle window that started on an account
                   since refilled. */
                cache()->forget('cupida-credit-low');
                cache()->forget('cupida-credit-exhausted');

                Notification::make()
                    ->title(__('cupida.admin.credit.saved', [
                        'threshold' => '$' . number_format((float)config('cupida.credit.warn_below'), 2),
                    ]))
                    ->success()
                    ->send();
            });
    }

    /**
     * Resolved per call rather than held, because the modal reads it again
     * after a save and a budget built before that would show the old number.
     *
     * Not named `badge()`: Filament's own `badge()` is an instance method on
     * Action, and one of ours by that name shadows it.
     */
    private function budget(): CupidaBudget
    {
        return new CupidaBudget(app(CupidaSettings::class));
    }
}
