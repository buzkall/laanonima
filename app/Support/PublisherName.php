<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A publisher's name as it should be printed, out of whatever a catalog wrote.
 *
 * The shop's own listing shouts about one imprint in eight -- "ALFAGUARA",
 * "EDICIONES VERSATIL, S.L." -- and `ImportShopBook` files a publisher row per
 * name it meets, so those capitals end up in the panel, on the book page and in
 * the sidebar next to the names a bookseller typed by hand.
 *
 * Same rule as `PersonName`: only a name written in nothing but capitals is
 * recased, because a source that bothered with capitals got the hard parts
 * right ("Duomo ediciones", "AdN", "Alpha decay") and there is no way back from
 * a name that has been flattened.
 *
 * What makes an imprint different from a person is everything that is not a
 * word: legal forms ("S.L.", "SLL"), initials ("DK", "T&T", "PPC"), a year, an
 * ampersand. Those are kept exactly as the catalog shouted them -- a rule that
 * would title-case "S.L." into "S.l." is worse than no rule at all.
 */
class PublisherName
{
    /**
     * Words that stay lowercase inside a name, in the languages the shop sells.
     */
    private const array PARTICLES = [
        'a', 'al', 'and', 'de', 'del', 'e', 'el', 'en', 'i', 'la', 'las',
        'los', 'of', 'the', 'y',
    ];

    /**
     * Initialisms a vowel makes look like words.
     *
     * Everything shorter than three letters is kept by the length rule and
     * everything without a vowel by the shape rule; this is the short list of
     * imprints those two miss. It is meant to grow -- add the acronym rather
     * than loosening a rule, which is how "DIEGO PUN EDICIONES" stays
     * "Diego Pun Ediciones".
     */
    private const array ACRONYMS = ['edaf', 'rba', 'uned', 'uve'];

    public static function normalize(string $name): string
    {
        /* The shop's publisher links come through double-encoded, so "Blatt
           &amp; Ríos" is what the scrape writes down. Decoding is not about
           capitals, but it is the same question -- what does this imprint call
           itself -- and this is the one place every name passes through. */
        $name = Str::squish(html_entity_decode($name, ENT_QUOTES | ENT_HTML5));

        /* One lowercase letter anywhere is proof the source cased it on purpose. */
        if ($name === '' || preg_match('/\p{Ll}/u', $name) === 1) {
            return $name;
        }

        $words = explode(' ', $name);

        foreach ($words as $position => $word) {
            $words[$position] = self::word($word, $position);
        }

        return implode(' ', $words);
    }

    /**
     * A comma is a space somebody forgot: "EDITORIAL ELBA,S.L." is two words
     * written as one, and the second of them is a legal form to keep.
     */
    private static function word(string $word, int $position): string
    {
        $parts = explode(',', $word);

        foreach ($parts as $index => $part) {
            $parts[$index] = self::part($part, $position + $index);
        }

        return implode(',', $parts);
    }

    private static function part(string $part, int $position): string
    {
        $lower = mb_strtolower($part);

        /* Asked before the initialism rules, which would keep "DE" and "LA" as
           they are on the strength of being two letters long. */
        if (in_array($lower, self::PARTICLES, true)) {
            return $position > 0 ? $lower : self::capitalize($lower);
        }

        return self::isInitialism($part) ? $part : self::capitalize($lower);
    }

    /**
     * Something that is not a word, and that lowercasing would spoil.
     *
     * Three shapes, in the order they turn up: anything with no letters in it
     * at all ("&", "1959"), anything with a period inside ("S.L.", "K.O"), and
     * a run of letters too short or too consonantal to be a word ("DK", "SLL",
     * "T&T", "PPC") -- plus the handful of acronyms that read like one.
     */
    private static function isInitialism(string $part): bool
    {
        $letters = (string)preg_replace('/[^\p{L}]/u', '', $part);

        if ($letters === '' || str_contains($part, '.')) {
            return true;
        }

        $lower = mb_strtolower($letters);

        return mb_strlen($letters) <= 2
            || preg_match('/[aeiou]/', Str::ascii($lower)) !== 1
            || in_array($lower, self::ACRONYMS, true);
    }

    private static function capitalize(string $lower): string
    {
        return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
    }
}
