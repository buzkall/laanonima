<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Filament\Resources\Cupida\CupidaResource;
use App\Support\Cupida\CupidaBudget;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The account is nearly empty, as far as this app can tell.
 *
 * "As far as it can tell" is the whole caveat: the figure is what a bookseller
 * typed in after topping up, minus what has been recorded against it since, so
 * it is exact while nothing else spends the key and optimistic otherwise. The
 * message says the number rather than an instruction for that reason.
 *
 * Nothing breaks when it is ignored. Past the end of the credit La Cupida keeps
 * handing over books, picked by the scoring, with the canned line instead of a
 * written one -- which is a page quietly getting worse rather than a page down,
 * and is exactly the kind of thing that goes unnoticed without this.
 *
 * Queued, like `CupidaCreditExhausted` and unlike the rest of this app's mail.
 * What triggers it is a reader swiping: the balance is checked inside their
 * request, and handing the mail to a worker keeps an unreachable SMTP host out
 * of the wait for their book. Both warnings therefore need a queue worker in
 * front of the site -- without one the job sits in `jobs` and nobody is ever
 * told, which is the failure they exist to prevent.
 */
class CupidaCreditRunningLow extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public float $remaining) {}

    /**
     * One channel each, decided by who is being told.
     *
     * The address out of the settings arrives as an AnonymousNotifiable, which
     * has no database to write a bell to; an admin user has one, and mailing
     * them as well would be the same warning twice for whoever is both.
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
            ->subject(__('cupida.credit.low.subject'))
            ->line(__('cupida.credit.low.body', ['remaining' => $this->money()]))
            ->line(__('cupida.credit.low.consequence'))
            ->action(__('cupida.credit.low.action'), CupidaResource::getUrl(panel: UserRole::Admin->panelId()));
    }

    /**
     * Filament reads the database channel, so the payload has to be its shape
     * rather than an array of our own.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('cupida.credit.low.subject'))
            ->body(__('cupida.credit.low.body', ['remaining' => $this->money()]))
            ->warning()
            ->getDatabaseMessage();
    }

    private function money(): string
    {
        /* The panel's own formatter, so a balance already spent past reads the
           same in the inbox as on the button: "-$3.50", not "$-3.50". */
        return CupidaBudget::money($this->remaining);
    }
}
