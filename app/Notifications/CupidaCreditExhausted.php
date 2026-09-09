<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Filament\Resources\Cupida\CupidaResource;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The provider refused a prompt for want of credit.
 *
 * Unlike the warning above this is not an estimate: it is Anthropic saying no,
 * caught as `InsufficientCreditsException` and reported before it is swallowed.
 * It is the one moment the app knows the balance for certain, and until now the
 * only trace of it was a line in the log.
 *
 * The page is still up and still recommending; what readers stopped getting is
 * the written pitch. Saying that in the mail matters, because "out of credit"
 * otherwise reads as an outage worth panicking about on a Saturday.
 *
 * Queued, for the reason in `CupidaCreditRunningLow`: the refusal is caught
 * inside a reader's swipe, and the mail must not put an unreachable SMTP host
 * in front of their book.
 */
class CupidaCreditExhausted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * One channel each: see CupidaCreditRunningLow.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return new MailMessage()
            ->subject(__('cupida.credit.exhausted.subject'))
            ->line(__('cupida.credit.exhausted.body'))
            ->line(__('cupida.credit.exhausted.consequence'))
            ->action(__('cupida.credit.exhausted.action'), CupidaResource::getUrl(panel: UserRole::Admin->panelId()));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('cupida.credit.exhausted.subject'))
            ->body(__('cupida.credit.exhausted.body'))
            ->danger()
            ->getDatabaseMessage();
    }
}
