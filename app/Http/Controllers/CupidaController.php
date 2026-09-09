<?php

namespace App\Http\Controllers;

use App\Support\CoverPalette;
use Illuminate\Contracts\View\View;

/**
 * The page is a shell: everything that happens on it happens in the Livewire
 * component. This is here so La Cupida gets its meta tags, its fonts and its
 * palette from the same layout as the other five pages rather than from a
 * Livewire layout of its own.
 */
class CupidaController extends Controller
{
    /**
     * @param  string|null  $guest  a key in cupida.guests, from /la-cupida/{guest}
     */
    public function __invoke(?string $guest = null): View
    {
        return view('cupida.index', [
            /* The page belongs to no book until there is a recommendation, so
               it opens in the house color like the shelf does. */
            'palette' => CoverPalette::fromCover(null),
            'guest'   => $this->guestName($guest),
        ]);
    }

    /**
     * Who /la-cupida/{guest} greets.
     *
     * Looked up in `cupida.guests` and never taken from the URL: this name is
     * written onto the shop's own public page, so a segment nobody put on the
     * list is a 404 rather than a word a stranger got to choose. Escaping it
     * would keep the page safe and still let /la-cupida/anything read as
     * something the shop said.
     */
    private function guestName(?string $guest): ?string
    {
        if ($guest === null) {
            return null;
        }

        $name = config('cupida.guests')[$guest] ?? null;

        abort_if($name === null, 404);

        return (string)$name;
    }
}
