<?php

namespace App\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\HtmlString;

/**
 * Why a reader is looking at the sign-in form they did not ask for.
 *
 * Asking us for a book is the only thing on the shop behind `auth`, so a guest
 * who presses "pídenoslo" -- on a book, in the footer, at the end of a Cupida
 * session -- is bounced to the client panel's login with nothing said. The form
 * they wanted is remembered as the intended URL and they are put back on it
 * afterwards, but until then the page is a password box with no reason on it,
 * which reads as having been thrown out rather than asked to knock.
 *
 * So the reason is read back off the intended URL and printed under the
 * heading. The URL is matched against the two request-form routes themselves
 * rather than against a path spelled out here: renaming `/pedir-libro` must not
 * quietly turn the explanation off.
 */
final class BookRequestSignIn
{
    /**
     * The auth page's own subheading, with the reason in front of it.
     *
     * Filament's is the "no account yet? register" line, which is exactly what
     * a reader in this position needs next, so it is kept and led into rather
     * than replaced. On its own line: that line opens with a lowercase "o",
     * which run straight on after a full stop reads as a typo. `display:block`
     * inline rather than a utility class -- the panel stylesheet is Filament's
     * own build and nothing guarantees it ships the one we would reach for.
     */
    public static function subheading(string|Htmlable|null $default): string|Htmlable|null
    {
        if (! self::isPending()) {
            return $default;
        }

        $tail = $default instanceof Htmlable ? $default->toHtml() : e((string)$default);

        $reason = '<span style="display:block">' . e(__('auth.book_request.reason')) . '</span>';

        return new HtmlString($reason . $tail);
    }

    /**
     * Whether the reader was turned away from the book request form to get here.
     */
    public static function isPending(): bool
    {
        $intended = session('url.intended');

        if (blank($intended)) {
            return false;
        }

        $request = Request::create((string)$intended);

        return array_any(
            ['book-requests.create', 'book-requests.create.book'],
            fn(string $name): bool => Router::getRoutes()->getByName($name)?->matches($request) ?? false,
        );
    }
}
