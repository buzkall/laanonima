<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells every search engine to leave the whole site out of its index.
 *
 * This is a demo shown to one shop, and it must not turn up in Google. A header
 * rather than a `<meta>` tag, because it covers every response the app sends --
 * the panels, the generated og:image cards, a redirect -- without each layout
 * having to remember it.
 *
 * `robots.txt` stays open on purpose: a crawler that is disallowed never fetches
 * the page, so it never reads this header, and a link to the site from elsewhere
 * can still get the bare URL listed. It also keeps link previews working for the
 * scrapers that honor `robots.txt` when somebody shares a page.
 */
class KeepSiteOutOfSearchEngines
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
