<?php

namespace App\Settings;

use Carbon\CarbonImmutable;
use Spatie\LaravelSettings\Settings;

/**
 * Everything about La Cupida a person changes without a deploy.
 *
 * Three unrelated things share this class because they share one property: a
 * bookseller owns them and a release cannot wait for them. What the shop wants
 * said, what it put on the account, and who hears when that runs out.
 *
 * A settings class rather than a table each. Every one of these is a single
 * value with no rows, no history and no relations, and a table for that is a
 * model, a migration and a `firstOrCreate()` standing in for a property. The
 * next one costs a line here.
 *
 * What is NOT here is the baseline prompt. `CupidaAgent::baseInstructions()`
 * stays in code because it is the part that keeps a recommendation honest --
 * choose from what you are given, invent nothing, never claim to be a person --
 * and it should only ever change with a deploy and a review.
 */
class CupidaSettings extends Settings
{
    /**
     * Appended to the baseline, and announced as coming from the shop: the
     * season, the table by the door, the writer they are pushing this month.
     */
    public ?string $extra_instructions = null;

    /**
     * What was last put on the Anthropic account, in dollars.
     *
     * Typed in because there is nothing to read: Anthropic publishes no balance,
     * and its reports say what was spent rather than what is left. Null means
     * nobody has said, which is not an empty account, and is why nothing is
     * watched until it is filled in.
     */
    public ?float $credit_balance = null;

    /*
     | When that happened, so the countdown knows which recommendations were
     | paid for by this balance and which by the one before it.
     |
     | A block comment and not a docblock, deliberately. `PropertyReflector`
     | reads the native type only when a property has no doc comment at all:
     | give one a `/**` and it looks for `@var`, and resolves nothing when there
     | is none -- so no cast is built and the raw string out of the JSON column
     | is assigned to a typed property. That surfaces as a TypeError on read,
     | nowhere near here. Writing `@var` instead works until Pint deletes it as
     | superfluous, which it does, so the comment is what changes rather than
     | the type.
     |
     | Concrete, too, rather than DateTimeInterface: the cast has to construct
     | what it hands back, and an interface tells it nothing to construct.
     */
    public ?CarbonImmutable $credit_topped_up_at = null;

    /**
     * Where the warnings go.
     *
     * Not the shop's public address: that one is printed on the book page and
     * answered by whoever is behind the counter, and an account running dry is
     * not something they can do anything about. Left empty it falls back to it
     * anyway, because a warning in the wrong inbox gets noticed and a warning
     * sent nowhere does not.
     */
    public ?string $admin_email = null;

    public static function group(): string
    {
        return 'cupida';
    }

    /**
     * Who to mail, with the shop as the fallback.
     */
    public function notificationEmail(): string
    {
        return filled($this->admin_email)
            ? $this->admin_email
            : (string)config('site.contact_email');
    }
}
