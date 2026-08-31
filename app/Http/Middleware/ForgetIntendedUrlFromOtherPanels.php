<?php

namespace App\Http\Middleware;

use App\Support\PanelUrl;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drops a remembered intended URL that belongs to a different panel.
 *
 * The panels share one session, so a guest who is turned away from `/admin` leaves
 * `/admin` behind as the intended URL. Anything that later calls
 * `redirect()->intended()` on another panel — signing in, consuming a magic link —
 * would follow it there and be refused. A request on this panel's routes has no use
 * for another panel's URL, so it is forgotten before it can be followed.
 *
 * A page of the shop itself is not another panel's and is kept: a reader sent
 * here to sign in before asking us for a book is meant to go back to the form.
 */
class ForgetIntendedUrlFromOtherPanels
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $intended = $request->session()->get('url.intended');

        if (filled($intended)
            && ! PanelUrl::isPublic($intended)
            && ! PanelUrl::belongsTo($intended, Filament::getCurrentOrDefaultPanel())) {
            $request->session()->forget('url.intended');
        }

        return $next($request);
    }
}
