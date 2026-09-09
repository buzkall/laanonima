<?php

namespace App\Support\Cupida;

use App\Models\CupidaRecommendation;
use App\Settings\CupidaSettings;
use DateTimeInterface;

/**
 * What is left on the Anthropic account, as far as this app can tell.
 *
 * "As far as it can tell" is the whole of it. Anthropic publishes no balance --
 * there is no endpoint to ask, and the admin reports say what was spent -- so
 * the only countdown available is what a bookseller typed in after topping up,
 * minus what has been recorded against it since. That is exact while nothing
 * else spends the key and quietly optimistic the moment something does.
 *
 * Which is why it only ever warns. What actually stops a prompt is the provider
 * refusing one, and RecommendBook already survives that.
 *
 * The arithmetic lives here rather than on CupidaSettings because a settings
 * class is a bag of values a person edits; a sum over another table is not one
 * of its values.
 */
final readonly class CupidaBudget
{
    public function __construct(private CupidaSettings $settings) {}

    /**
     * What has been spent since the last top-up.
     *
     * Only recommendations count, because they are the only thing here that
     * prompts. Rows written before the top-up were paid for by the previous
     * balance and would take the same dollar off twice.
     *
     * No date is not "count everything". A balance with nothing beside it says
     * a figure was written down and not when, and summing the whole table
     * against it charges this balance for every dollar the shop ever spent --
     * silently, and by more the longer La Cupida has been up. Nothing is
     * counted until there is a date to count from; EditCupidaCreditAction
     * stamps one whenever a balance is saved, so a settings row can only reach
     * this state by being written some other way.
     */
    public function spent(): float
    {
        /* DateTimeInterface, not Carbon: dates come back immutable in this app,
           and a Carbon hint would reject them at runtime only. */
        if (! $this->settings->credit_topped_up_at instanceof DateTimeInterface) {
            return 0.0;
        }

        return (float)CupidaRecommendation::query()
            ->where('created_at', '>=', $this->settings->credit_topped_up_at)
            ->sum('cost');
    }

    /**
     * What is left, or null while nobody has said what was put on.
     *
     * Null is the ordinary state of a fresh checkout and has to stay
     * distinguishable from zero: one means nothing is being watched, the other
     * means the account is empty.
     */
    public function remaining(): ?float
    {
        if ($this->settings->credit_balance === null) {
            return null;
        }

        return round($this->settings->credit_balance - $this->spent(), precision: 2);
    }

    /**
     * Whether what is left has fallen under `cupida.credit.warn_below`.
     *
     * False while no balance has been entered: a shop that has not told us what
     * it put on has not asked to be warned.
     */
    public function runningLow(): bool
    {
        $remaining = $this->remaining();

        return $remaining !== null
            && $remaining <= (float)config('cupida.credit.warn_below');
    }

    /**
     * What is left, written the way the panel shows it, or nothing while no
     * balance has been entered.
     */
    public function label(): ?string
    {
        $remaining = $this->remaining();

        return $remaining === null ? null : self::money($remaining);
    }

    /**
     * A dollar figure, with the sign in front of the symbol and not after it.
     *
     * It can be negative: the countdown only ever warns, so an account is
     * routinely spent past the number the shop typed in. `'$' . number_format()`
     * writes that as "$-3.50", which reads as a price rather than as a debt.
     */
    public static function money(float $amount): string
    {
        return ($amount < 0 ? '-$' : '$') . number_format(abs($amount), 2);
    }
}
