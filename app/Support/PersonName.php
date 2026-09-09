<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A person's name as it should be printed, out of whatever a catalog wrote.
 *
 * Metadata sources file people the way a card index does -- "IAN. MCEWAN",
 * shouted, with the period a forename picked up from being written "MCEWAN,
 * IAN." somewhere upstream. That name then goes on the book page and into the
 * authors line, so it is tidied once, on the way in.
 *
 * Only a name written in nothing but capitals is recased. A source that
 * bothered with capitals got the hard parts right ("de la Fuente", "McEwan"),
 * and there is no way back from "MCEWAN" to anything but a rule.
 */
class PersonName
{
    /**
     * Words that stay lowercase inside a name, in the languages the shop sells.
     */
    private const array PARTICLES = [
        'a', 'da', 'das', 'de', 'del', 'den', 'der', 'di', 'do', 'dos', 'du',
        'e', 'el', 'la', 'las', 'le', 'les', 'los', 'ter', 'van', 'von', 'y',
    ];

    public static function normalize(string $name): string
    {
        $name = Str::squish($name);

        if ($name === '') {
            return '';
        }

        /* One capital anywhere is proof the source cased the name on purpose. */
        $shouted = preg_match('/\p{Ll}/u', $name) !== 1;

        $words = explode(' ', $name);

        foreach ($words as $position => $word) {
            $words[$position] = self::word($word, $shouted, $position);
        }

        return implode(' ', $words);
    }

    /**
     * A period belongs to an initial, not to a name.
     *
     * "J." and the "VV. AA." that stands in for a collective keep theirs;
     * "IAN." loses one it never had a reason for.
     */
    private static function word(string $word, bool $shouted, int $position): string
    {
        $bare = rtrim($word, '.');

        if (mb_strlen($bare) >= 3) {
            $word = $bare;
        }

        if (! $shouted || str_ends_with($word, '.')) {
            return $word;
        }

        $lower = mb_strtolower($word);

        if ($position > 0 && in_array($lower, self::PARTICLES, true)) {
            return $lower;
        }

        return self::capitalize($lower);
    }

    /**
     * Title case, plus the two prefixes it gets wrong: a capital follows "Mc"
     * and an apostrophe that opens a name ("McEwan", "O'Brien", "D'Ors").
     */
    private static function capitalize(string $lower): string
    {
        $word = mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');

        return (string)preg_replace_callback(
            '/^(Mc|\p{Lu}\')(\p{Ll})/u',
            fn(array $matches): string => $matches[1] . mb_strtoupper($matches[2], 'UTF-8'),
            $word,
        );
    }
}
