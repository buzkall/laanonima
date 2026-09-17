<?php

namespace App\Support;

/**
 * Text folded so a reader need not type it the way a catalog wrote it.
 *
 * The public search runs a plain `like` against `books.search_text`, which is
 * this fold of everything a reader might look a book up by. Folding on the way
 * in and on the way out is what makes "garcia marquez" find "García Márquez"
 * identically on Postgres and SQLite, neither of which agrees with the other
 * about case or accents in a `like`.
 */
class SearchText
{
    /**
     * Every accent a Spanish catalog actually contains, plus the Catalan and
     * French ones the shop's imprints bring with them.
     */
    private const array ACCENTS = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    /**
     * Lowercase and unaccented.
     *
     * Spelled out rather than done with `Str::ascii()`, which is a general
     * transliterator with a large table behind it: La Cupida runs this over
     * every title and synopsis in the pool on every recommendation, and at
     * that size the general answer costs about seven times what this one does.
     */
    public static function fold(string $text): string
    {
        return strtr(mb_strtolower($text), self::ACCENTS);
    }

    /**
     * The words of a query, folded, with the punctuation between them dropped.
     *
     * Only letters and digits survive, so a term can never carry a `%` or `_`
     * into the `like` it is matched with.
     *
     * @return list<string>
     */
    public static function terms(string $query): array
    {
        return array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', self::fold($query)) ?: [],
            fn(string $term): bool => $term !== '',
        ));
    }

    /**
     * The query as an ISBN, when that is what was typed: a reader copies one
     * off a book with its hyphens, and the column stores it without.
     */
    public static function isbn(string $query): ?string
    {
        $compact = strtoupper((string)preg_replace('/[\s-]+/', '', $query));

        return preg_match('/^(\d{13}|\d{9}[\dX])$/', $compact) === 1 ? $compact : null;
    }
}
