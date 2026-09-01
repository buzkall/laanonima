<?php

namespace App\Support\BookMetadata;

/**
 * How the free text a provider calls a measurement becomes millimetres and
 * grams.
 *
 * Nothing arrives in a fixed unit. Open Library files one string for the whole
 * object ("8.5 x 5.4 x 0.8 inches"), Google Books files three labelled ones
 * ("23.00 cm"), and the underlying records were imported from catalogues on
 * both sides of the Atlantic, so inches and centimetres are equally common.
 */
final class PhysicalMeasure
{
    /**
     * How many millimetres one unit of each name is worth.
     *
     * Longest name first: the patterns are matched in order, so "centimeters"
     * has to be tried before "cm" would swallow its first two letters.
     *
     * @var array<string, float>
     */
    private const array LENGTH_UNITS = [
        'millimeters' => 1.0,
        'millimetres' => 1.0,
        'centimeters' => 10.0,
        'centimetres' => 10.0,
        'inches'      => 25.4,
        'inch'        => 25.4,
        'mm'          => 1.0,
        'cm'          => 10.0,
        'in'          => 25.4,
        '"'           => 25.4,
    ];

    /**
     * @var array<string, float>
     */
    private const array WEIGHT_UNITS = [
        'kilograms' => 1000.0,
        'kilogram'  => 1000.0,
        'pounds'    => 453.592,
        'pound'     => 453.592,
        'ounces'    => 28.3495,
        'ounce'     => 28.3495,
        'grams'     => 1.0,
        'gram'      => 1.0,
        'kg'        => 1000.0,
        'lbs'       => 453.592,
        'lb'        => 453.592,
        'oz'        => 28.3495,
        'g'         => 1.0,
    ];

    /**
     * The junk in a free-text field is rarely subtle: a record that gives a book
     * as 2m tall or 1mm wide is a bad import rather than an unusual edition.
     *
     * The floor is different for a side than for a measurement in general. No
     * book is narrower than a postcard, but plenty are 5mm thick, so a lone
     * measurement is only held to the loose bound and the two long sides are
     * held to the strict one.
     */
    private const int MIN_LENGTH_MM = 3;

    private const int MIN_SIDE_MM = 40;
    private const int MAX_LENGTH_MM = 500;
    private const int MIN_WEIGHT_G = 10;
    private const int MAX_WEIGHT_G = 20000;

    /**
     * The three sides of a book out of one string, in millimetres.
     *
     * The order the numbers come in is not trustworthy -- the same field holds
     * records imported from library catalogues, from Amazon and from hand
     * edits, and they do not agree on whether height or width leads -- so the
     * sides are assigned by size instead: the longest is the height, the
     * shortest of three is the thickness. That is right for every book that is
     * taller than it is wide, which is very nearly all of them, and it cannot
     * silently transpose a whole source the way a fixed order can.
     *
     * @return array{height: int, width: int, thickness: int|null}|null
     */
    public static function dimensionsInMm(mixed $text): ?array
    {
        if (! is_string($text)) {
            return null;
        }

        $factor = self::lengthFactor($text);

        if ($factor === null) {
            return null;
        }

        preg_match_all('/\d+(?:[.,]\d+)?/', $text, $matches);

        $sides = array_values(array_filter(
            array_map(
                fn(string $number): int => (int)round((float)str_replace(',', '.', $number) * $factor),
                $matches[0],
            ),
            fn(int $mm): bool => $mm > 0,
        ));

        if (count($sides) < 2) {
            return null;
        }

        rsort($sides);

        [$height, $width] = $sides;
        $thickness = $sides[2] ?? null;

        if (! self::isPlausibleSide($height) || ! self::isPlausibleSide($width)) {
            return null;
        }

        return [
            'height'    => $height,
            'width'     => $width,
            'thickness' => $thickness !== null && self::isPlausibleLength($thickness) ? $thickness : null,
        ];
    }

    /**
     * One labelled measurement -- "23.00 cm" -- in millimetres.
     */
    public static function lengthInMm(mixed $text): ?int
    {
        if (! is_string($text) || preg_match('/\d+(?:[.,]\d+)?/', $text, $matches) !== 1) {
            return null;
        }

        $factor = self::lengthFactor($text);

        if ($factor === null) {
            return null;
        }

        $mm = (int)round((float)str_replace(',', '.', $matches[0]) * $factor);

        return self::isPlausibleLength($mm) ? $mm : null;
    }

    public static function weightInGrams(mixed $text): ?int
    {
        if (! is_string($text) || preg_match('/\d+(?:[.,]\d+)?/', $text, $matches) !== 1) {
            return null;
        }

        $factor = self::unitFactor($text, self::WEIGHT_UNITS);

        if ($factor === null) {
            return null;
        }

        $grams = (int)round((float)str_replace(',', '.', $matches[0]) * $factor);

        return $grams >= self::MIN_WEIGHT_G && $grams <= self::MAX_WEIGHT_G ? $grams : null;
    }

    /**
     * A measurement with no unit written on it is not assumed to be anything.
     * Both centimetres and inches are common enough here that a guess would be
     * wrong for a large minority of records, and a book listed at 210 inches is
     * worse than a book with no measurements at all.
     */
    private static function lengthFactor(string $text): ?float
    {
        return self::unitFactor($text, self::LENGTH_UNITS);
    }

    /**
     * @param  array<string, float>  $units
     */
    private static function unitFactor(string $text, array $units): ?float
    {
        $haystack = mb_strtolower($text);

        foreach ($units as $unit => $factor) {
            if (str_contains($haystack, $unit)) {
                return $factor;
            }
        }

        return null;
    }

    private static function isPlausibleLength(int $mm): bool
    {
        return $mm >= self::MIN_LENGTH_MM && $mm <= self::MAX_LENGTH_MM;
    }

    private static function isPlausibleSide(int $mm): bool
    {
        return $mm >= self::MIN_SIDE_MM && $mm <= self::MAX_LENGTH_MM;
    }
}
