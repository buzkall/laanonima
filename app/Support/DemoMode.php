<?php

namespace App\Support;

/**
 * The catch on the panels while the shop is showing itself off.
 *
 * The demo is open: anyone with the address signs in and walks the admin panel,
 * which is the point of it and also the risk of it -- one bored visitor can
 * empty the catalog in a couple of clicks, and there is no undo behind a delete
 * because nothing here is soft-deleted.
 *
 * So it is a catch and not a role: the abilities below are refused to everybody,
 * administrator included, and the panels ask the same policies they always did.
 * Filament hides an action it cannot authorize, so a demo visitor never sees a
 * delete button rather than being told off after clicking one.
 *
 * It is deliberately the whole of the answer. Adding a resource, a panel or a
 * bulk action cannot forget to opt in, because the refusal happens at the gate
 * every one of them goes through (see `AppServiceProvider::blockDestructiveAbilitiesInDemoMode`).
 */
class DemoMode
{
    /**
     * Everything a visitor may not do while the demo is open.
     *
     * The four delete abilities are Filament's: `delete` is the row and page
     * action, `deleteAny` the bulk one, and the force pair is what a
     * soft-deleting resource would ask instead. No model soft-deletes today and
     * they cost nothing to refuse in advance.
     *
     * `withdraw` is the one entry that is not a delete. It is the only action
     * the client panel has, it cancels a request and mails the shop about it,
     * and neither half of that is something a stranger poking at the demo
     * should be able to do to a real reader's order.
     *
     * @var list<string>
     */
    public const array BLOCKED_ABILITIES = [
        'delete',
        'deleteAny',
        'forceDelete',
        'forceDeleteAny',
        'withdraw',
    ];

    /**
     * On by default, and off by naming it: the cost of forgetting the variable
     * on the demo box is a wiped catalog, and the cost of forgetting it in
     * production is a bookseller who cannot delete a typo.
     */
    public static function enabled(): bool
    {
        return (bool)config('site.demo_mode');
    }

    public static function forbids(string $ability): bool
    {
        return self::enabled() && in_array($ability, self::BLOCKED_ABILITIES, true);
    }
}
