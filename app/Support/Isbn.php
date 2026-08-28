<?php

namespace App\Support;

/**
 * ISBN normalisation and check-digit validation.
 *
 * Booksellers type ISBNs from a book's back cover, hyphens and all, and often
 * from an older edition that still carries a 10-digit number.
 */
class Isbn
{
    /**
     * Strip hyphens, spaces and any other separators, upper-casing the X check digit.
     */
    public static function normalize(?string $isbn): string
    {
        return strtoupper(preg_replace('/[^0-9xX]/', '', (string)$isbn) ?? '');
    }

    /**
     * Convert any valid ISBN-10 or ISBN-13 into its 13-digit form.
     */
    public static function toIsbn13(?string $isbn): ?string
    {
        $normalized = self::normalize($isbn);

        if (strlen($normalized) === 13) {
            return self::isValidIsbn13($normalized) ? $normalized : null;
        }

        if (strlen($normalized) === 10 && self::isValidIsbn10($normalized)) {
            $body = '978' . substr($normalized, 0, 9);

            return $body . self::isbn13CheckDigit($body);
        }

        return null;
    }

    /**
     * Convert a 978-prefixed ISBN-13 back to its 10-digit form. 979 prefixes have no ISBN-10.
     */
    public static function toIsbn10(?string $isbn): ?string
    {
        $isbn13 = self::toIsbn13($isbn);

        if ($isbn13 === null || ! str_starts_with($isbn13, '978')) {
            return null;
        }

        $body = substr($isbn13, 3, 9);

        return $body . self::isbn10CheckDigit($body);
    }

    public static function isValid(?string $isbn): bool
    {
        return self::toIsbn13($isbn) !== null;
    }

    public static function isValidIsbn13(string $normalized): bool
    {
        if (! preg_match('/^\d{13}$/', $normalized)) {
            return false;
        }

        return substr($normalized, -1) === self::isbn13CheckDigit(substr($normalized, 0, 12));
    }

    public static function isValidIsbn10(string $normalized): bool
    {
        if (! preg_match('/^\d{9}[0-9X]$/', $normalized)) {
            return false;
        }

        return substr($normalized, -1) === self::isbn10CheckDigit(substr($normalized, 0, 9));
    }

    private static function isbn13CheckDigit(string $first12): string
    {
        $sum = 0;

        foreach (str_split($first12) as $position => $digit) {
            $sum += (int)$digit * ($position % 2 === 0 ? 1 : 3);
        }

        return (string)((10 - $sum % 10) % 10);
    }

    private static function isbn10CheckDigit(string $first9): string
    {
        $sum = 0;

        foreach (str_split($first9) as $position => $digit) {
            $sum += (int)$digit * (10 - $position);
        }

        $remainder = (11 - $sum % 11) % 11;

        return $remainder === 10 ? 'X' : (string)$remainder;
    }
}
