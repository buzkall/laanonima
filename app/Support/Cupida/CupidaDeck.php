<?php

namespace App\Support\Cupida;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The rounds of cards a reader swipes.
 *
 * Built from one seed, the way `ShelfArrangement` builds the shelf, so a
 * session can be put back exactly as it was -- which is what makes the deck
 * testable at all, and what lets a screenshot be retaken.
 *
 * The rounds are deliberately different in kind. Subjects are what the shop
 * files a book under, authors are who wrote it, and moods are the only question
 * a catalog cannot answer -- they are the reason the deck feels like a
 * conversation rather than a filter.
 */
final readonly class CupidaDeck
{
    public const array ROUNDS = ['theme', 'author', 'mood'];

    /**
     * @param  array<int, array<int, CupidaCard>>  $rounds
     */
    private function __construct(
        /** Reproduces this exact deck. */
        public int $seed,
        public array $rounds,
    ) {}

    public static function for(CupidaCatalog $catalog, ?int $seed = null): self
    {
        $seed ??= random_int(0, PHP_INT_MAX);
        $randomizer = new Randomizer(new Mt19937($seed));

        $size = (int)config('cupida.deck.size');

        return new self(seed: $seed, rounds: [
            self::themeCards($catalog, $randomizer, $size),
            self::authorCards($catalog, $randomizer, $size),
            self::moodCards($randomizer, $size),
        ]);
    }

    /**
     * @return array<int, CupidaCard>
     */
    public function round(int $index): array
    {
        return $this->rounds[$index] ?? [];
    }

    public function rounds(): int
    {
        return count($this->rounds);
    }

    /**
     * @return array<int, CupidaCard>
     */
    private static function themeCards(CupidaCatalog $catalog, Randomizer $randomizer, int $size): array
    {
        $themes = array_slice($randomizer->shuffleArray($catalog->deckThemes()), 0, $size);

        return self::paint($randomizer, array_map(
            fn(array $theme): array => [
                'kind'  => 'theme',
                'key'   => (string)$theme['code'],
                'label' => (string)$theme['label'],
                'note'  => trans_choice('cupida.cards.stocked', (int)$theme['books'], ['count' => $theme['books']]),
            ],
            $themes,
        ));
    }

    /**
     * The authors round, drawn off the top of the catalog rather than out of
     * all of it.
     *
     * A flat shuffle of every name the shop stocks is a deck of six writers
     * nobody has heard of, because the long tail is almost all of the list. So
     * the draw is from the best-stocked `pool` names -- which is where the
     * writers a reader might recognize are -- and the shuffle happens inside
     * that.
     *
     * Collectives are dropped first: see `CupidaCatalog::isCollective()`.
     *
     * @return array<int, CupidaCard>
     */
    private static function authorCards(CupidaCatalog $catalog, Randomizer $randomizer, int $size): array
    {
        /* Before the slice, not after: the shop files anthologies under an
           author name, so the pool carries "Vv. Aa." and "Varios Autores"
           among the writers, and a byline like Carmen Mola is three people.
           None of them is a question a reader can answer by swiping. Dropping
           them first also keeps the pool a hundred and fifty real writers deep
           rather than a hundred and forty-five. */
        $authors = array_values(array_filter(
            $catalog->authors(),
            fn(array $author): bool => ! $catalog->isCollective((string)$author['slug']),
        ));

        $authors = array_slice($authors, 0, (int)config('cupida.deck.author_pool'));
        $authors = array_slice($randomizer->shuffleArray($authors), 0, $size);

        /* The portrait is looked up here, after both randomizer calls, so the
           sequence of draws is untouched by whether we happen to have a face. */
        return self::paint($randomizer, array_map(
            fn(array $author): array => [
                'kind'     => 'author',
                'key'      => (string)$author['slug'],
                'label'    => (string)$author['name'],
                'note'     => is_string($author['titles'][0] ?? null) ? $author['titles'][0] : null,
                'portrait' => $catalog->portrait((string)$author['slug']),
            ],
            $authors,
        ));
    }

    /**
     * @return array<int, CupidaCard>
     */
    private static function moodCards(Randomizer $randomizer, int $size): array
    {
        $keys = array_keys((array)config('cupida.moods'));

        return self::paint($randomizer, array_map(
            fn(string $key): array => [
                'kind'  => 'mood',
                'key'   => $key,
                'label' => (string)__("cupida.moods.{$key}"),
                'note'  => null,
            ],
            array_slice($randomizer->shuffleArray($keys), 0, $size),
        ));
    }

    /**
     * Give each card its color, never the same one twice in a row.
     *
     * Two neighboring cards in the same paint reads as the deck failing to
     * advance -- the card flies off and the one behind it looks identical --
     * which is the whole feedback the gesture has.
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<int, CupidaCard>
     */
    private static function paint(Randomizer $randomizer, array $cards): array
    {
        /** @var array<int, string> $palette */
        $palette = $randomizer->shuffleArray((array)config('cupida.deck.colors'));

        $painted = [];
        $previous = null;

        foreach (array_values($cards) as $index => $card) {
            $color = $palette[$index % count($palette)];

            if ($color === $previous) {
                $color = $palette[($index + 1) % count($palette)];
            }

            $painted[] = CupidaCard::make(
                kind: (string)$card['kind'],
                key: (string)$card['key'],
                label: (string)$card['label'],
                note: is_string($card['note'] ?? null) ? $card['note'] : null,
                color: $color,
                portrait: ($card['portrait'] ?? null) instanceof CupidaPortrait ? $card['portrait'] : null,
            );

            $previous = $color;
        }

        return $painted;
    }
}
