<?php

namespace App\Actions\Publishers;

use App\Models\Publisher;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Fold duplicate publisher rows into the one worth keeping.
 *
 * The shop writes the same imprint several ways -- "CAJA NEGRA" and "CAJA NEGRA
 * EDITORIAL", "Editorial Anagrama" and "Editorial Anagrama S.A." -- and
 * `Publisher::named()` files a row per spelling because a slug is all it has to
 * go on. Tidying the capitals closes the duplicates that were only capitals;
 * the rest are somebody's judgment about whether two names are one publisher,
 * which is what this is for.
 *
 * One survivor, any number absorbed. The books move first and the row goes
 * afterwards, inside a transaction: `publisher_id` is `nullOnDelete`, so a
 * delete that ran on its own would quietly leave a shelf of books with no
 * publisher at all rather than failing.
 */
class MergePublishers
{
    /**
     * @param  iterable<int, Publisher>  $absorbed
     * @return int books moved onto the survivor
     */
    public function __invoke(Publisher $survivor, iterable $absorbed): int
    {
        return DB::transaction(function() use ($survivor, $absorbed): int {
            $moved = 0;

            foreach ($absorbed as $publisher) {
                /* Absorbing a publisher into itself would delete it and every
                   book with it. Nothing in the panel offers that, and this is
                   not the place to find out whether something else does. */
                if ($publisher->is($survivor)) {
                    continue;
                }

                $moved += $publisher->books()->update(['publisher_id' => $survivor->id]);

                $this->carryOver($survivor, $publisher);

                $publisher->delete();
            }

            return $moved;
        });
    }

    /**
     * What the absorbed row knew and the survivor does not.
     *
     * A merge is not an edit, so nothing already filled in is touched: the
     * survivor is the name a bookseller chose to keep and its own website and
     * description go with it. What would otherwise be thrown away -- the
     * logotype somebody uploaded onto the duplicate, a website typed on the
     * wrong row -- moves across instead of dying with the record.
     */
    private function carryOver(Publisher $survivor, Publisher $absorbed): void
    {
        $gaps = array_filter(
            ['website' => $absorbed->website, 'description' => $absorbed->description],
            fn(?string $value, string $field): bool => filled($value) && blank($survivor->{$field}),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($gaps !== []) {
            $survivor->update($gaps);
        }

        $logo = $absorbed->getFirstMedia(Publisher::LOGO_COLLECTION);

        if ($logo instanceof Media && ! $survivor->hasMedia(Publisher::LOGO_COLLECTION)) {
            $logo->move($survivor, Publisher::LOGO_COLLECTION);
        }
    }
}
