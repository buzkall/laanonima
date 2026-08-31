<?php

namespace App\Filament\Client\Resources\BookRequests\Actions;

use App\Enums\BookRequestStatus;
use App\Mail\BookRequestWithdrawn;
use App\Models\BookRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

/**
 * The one thing a reader may do to a request of their own: call it off.
 *
 * It is not an edit -- nothing they wrote changes -- so the shop's record of
 * what was asked for stays intact and only the status moves. The shop is told
 * by email rather than left to notice on a screen: the book may already be on
 * its way from the distributor.
 */
class WithdrawBookRequestAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'withdraw';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('book_requests.actions.withdraw'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->iconButton()
            ->requiresConfirmation()
            ->modalHeading(__('book_requests.actions.withdraw_heading'))
            ->modalDescription(__('book_requests.actions.withdraw_description'))
            ->modalSubmitActionLabel(__('book_requests.actions.withdraw_confirm'))
            ->authorize(fn(BookRequest $record): bool => auth()->user()?->can('withdraw', $record) ?? false)
            ->action(function(BookRequest $record): void {
                $record->update(['status' => BookRequestStatus::Descartado]);

                Mail::to(config('site.contact_email'))->send(new BookRequestWithdrawn($record));

                Notification::make()
                    ->success()
                    ->title(__('book_requests.actions.withdrawn_title'))
                    ->body(__('book_requests.actions.withdrawn_body'))
                    ->send();
            });
    }
}
