<?php

namespace App\Actions\Cupida;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\CupidaCreditExhausted;
use App\Notifications\CupidaCreditRunningLow;
use App\Settings\CupidaSettings;
use App\Support\Cupida\CupidaBudget;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;
use Throwable;

/**
 * Tells the shop before the pitches quietly stop being written.
 *
 * Running out of credit is not an outage. The page stays up, the reader still
 * gets a book, and the only difference is that the sentence explaining it comes
 * from `cupida.result.fallback_pitch` instead of from a bookseller who has read
 * it. That is a page getting worse without anything looking broken, which is
 * why it is worth a mail.
 *
 * Two warnings, and they know different things. The estimate is ours: the
 * balance a bookseller typed in, minus what has been recorded since. The
 * refusal is Anthropic's, and it is the only moment either of us is certain.
 *
 * Both are throttled through the cache, because the trigger is a reader
 * swiping: an unthrottled warning would be one mail per swipe for as long as
 * the balance stayed low, and the second mail is already one too many.
 */
class WatchCupidaCredit
{
    /**
     * After a prompt that was paid for.
     */
    public function afterSpending(): void
    {
        $budget = new CupidaBudget(app(CupidaSettings::class));

        if (! $budget->runningLow()) {
            return;
        }

        $this->send('low', new CupidaCreditRunningLow((float)$budget->remaining()));
    }

    /**
     * After the provider refused one.
     */
    public function afterRefusal(): void
    {
        $this->send('exhausted', new CupidaCreditExhausted);
    }

    /**
     * One warning per throttle window, and never at the cost of a reader's book.
     *
     * `Cache::add` is the lock: it writes only when the key is absent, so the
     * first swipe past the line sends and the rest of the afternoon does not.
     * Everything from there is wrapped, because a mail server having a bad day
     * must not turn into a 500 on a public page.
     *
     * Two sends, because the two channels are addressed to different people.
     * The bell belongs to whoever is signed into the panel, so that goes to the
     * admin users; the mail goes to `site.admin_email`, which is whoever looks
     * after the site and may well have no account here at all.
     * `via()` on each notification is what stops anybody getting both.
     */
    private function send(string $key, Notification $notification): void
    {
        try {
            if (! Cache::add("cupida-credit-{$key}", true, (int)config('cupida.credit.throttle'))) {
                return;
            }

            Notifier::route('mail', $this->warningAddress())
                ->notify($notification);

            $admins = User::query()->where('role', UserRole::Admin)->get();

            if ($admins->isNotEmpty()) {
                Notifier::send($admins, $notification);
            }
        } catch (Throwable $exception) {
            Log::warning('La Cupida could not warn anybody about the credit.', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Whoever looks after the site, with the shop as the fallback.
     *
     * `site.admin_email` is an environment variable rather than a setting: the
     * address belongs to whoever deploys this, not to a bookseller between
     * top-ups. Unset -- a fresh checkout, a forgotten line in the deploy
     * environment -- the warning goes to the shop's own inbox instead, because
     * one read by the wrong person gets acted on and one sent nowhere does not.
     */
    private function warningAddress(): string
    {
        return (string)(config('site.admin_email') ?: config('site.contact_email'));
    }
}
