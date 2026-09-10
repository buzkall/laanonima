<?php

namespace App\Http\Controllers;

use App\Models\CupidaRecommendation;
use App\Support\Cupida\Recommendation;
use App\Support\Og\OgCard;
use App\Support\Og\OgCardKey;
use App\Support\Og\OgCardStore;
use Illuminate\Contracts\View\View;

/**
 * One recommendation, on a page a reader can send somebody.
 *
 * What makes this worth a page of its own is that the pitch was written for one
 * session and exists nowhere else -- sending the book's page instead throws
 * away the only part that was not already on the shelf.
 *
 * **The key is the authorization, and it is the whole of it.** There is no gate
 * here on purpose: `CupidaRecommendationPolicy` answers `isBookseller()` and
 * takes a non-null `User`, so routing a public page through it would refuse
 * every reader who was handed the link. What keeps one reader's session out of
 * another's hands is that the id is a ULID rather than a serial -- which is why
 * the key must never be swapped back for something countable.
 *
 * And what the page draws is the book and the writing about it. Never the
 * reader: `user_id`, `likes` and `passes` are on the row and none of them
 * reaches the view.
 */
class SharedRecommendationController extends Controller
{
    public function __construct(private readonly OgCardStore $shareCards = new OgCardStore) {}

    public function __invoke(CupidaRecommendation $recommendation): View
    {
        $recommendation->loadMissing('book.media');

        $drawn = Recommendation::fromRecord($recommendation);

        return view('cupida.shared', [
            'recommendation' => $drawn,
            'palette'        => $drawn->palette,
            'shareCard'      => $this->shareCards->url(
                OgCardKey::forRecommendation($recommendation),
                fn(): OgCard => OgCard::forRecommendation($recommendation),
            ),
        ]);
    }
}
