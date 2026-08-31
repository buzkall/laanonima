<?php

namespace App\Support;

/**
 * The colours a book's page is painted with, derived from its cover.
 *
 * The design is one flat colour taken off the cover plus cream and ink, so the
 * only thing that varies from book to book is that colour -- and the two
 * decisions that follow from it: what to write on top of it, and how dark it
 * has to get before it reads as a link on the cream.
 *
 * Both decisions are made by contrast ratio rather than by a lightness
 * threshold, because the averaged colours the covers produce land all over the
 * place: a washed pink for one book, a near-black brown for the next.
 */
final readonly class CoverPalette
{
    /** The red of the reference design, for a book with no cover to read. */
    public const string FALLBACK = '#e22314';

    /** Cream page and ink text: the two colours never derived from a cover. */
    public const string PAPER = '#f4efe4';

    public const string INK = '#211511';

    /** Cream over a colour, a shade warmer than the page. */
    public const string CREAM = '#f7f0e1';

    /** WCAG AA for body text. */
    private const float MIN_CONTRAST = 4.5;

    /** How much of the colour survives each darkening step. */
    private const float DARKEN_STEP = 0.88;

    private function __construct(
        /** The flat colour behind the hero, the top bar and the publisher band. */
        public string $background,
        /** Cream or ink, whichever can be read over the background. */
        public string $foreground,
        /** The background darkened until it can be read over the cream page. */
        public string $accent,
    ) {}

    public static function fromCover(?string $color): self
    {
        $background = self::normalize($color) ?? self::FALLBACK;

        return new self(
            background: $background,
            foreground: self::mostReadableOver($background),
            accent: self::darkenUntilReadable($background),
        );
    }

    /**
     * A colour to draw rules and muted text with, over the background.
     */
    public function foregroundFaded(float $opacity = 0.45): string
    {
        return sprintf('color-mix(in srgb, %s %d%%, transparent)', $this->foreground, (int)round($opacity * 100));
    }

    /**
     * Only the "#rrggbb" that ExtractCoverColor writes is accepted; anything
     * else -- a null, a legacy value, a hand-edited row -- falls back.
     */
    private static function normalize(?string $color): ?string
    {
        $candidate = mb_strtolower(trim((string)$color));

        return preg_match('/^#[0-9a-f]{6}$/', $candidate) === 1 ? $candidate : null;
    }

    private static function mostReadableOver(string $background): string
    {
        return self::contrast($background, self::CREAM) >= self::contrast($background, self::INK)
            ? self::CREAM
            : self::INK;
    }

    /**
     * Walk the colour towards black until it stands out on the cream page.
     *
     * Scaling all three channels by the same factor keeps the hue, so a pale
     * pink cover yields a deep rose rather than a generic dark grey. The loop
     * always terminates: every step is a strict darkening, and black clears the
     * threshold against cream.
     */
    private static function darkenUntilReadable(string $color): string
    {
        [$red, $green, $blue] = self::channels($color);

        while (self::contrast(self::hex($red, $green, $blue), self::PAPER) < self::MIN_CONTRAST) {
            $darker = array_map(fn(int $channel): int => (int)floor($channel * self::DARKEN_STEP), [$red, $green, $blue]);

            if ($darker === [$red, $green, $blue]) {
                return self::INK;
            }

            [$red, $green, $blue] = $darker;
        }

        return self::hex($red, $green, $blue);
    }

    /**
     * WCAG 2.1 relative contrast between two colours, from 1 to 21.
     */
    private static function contrast(string $first, string $second): float
    {
        $lighter = max(self::luminance($first), self::luminance($second));
        $darker = min(self::luminance($first), self::luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * WCAG 2.1 relative luminance.
     */
    private static function luminance(string $color): float
    {
        [$red, $green, $blue] = array_map(
            function(int $channel): float {
                $srgb = $channel / 255;

                return $srgb <= 0.04045 ? $srgb / 12.92 : (($srgb + 0.055) / 1.055) ** 2.4;
            },
            self::channels($color),
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    /**
     * @return array{int, int, int}
     */
    private static function channels(string $color): array
    {
        $rgb = (int)hexdec(mb_substr($color, 1));

        return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
    }

    private static function hex(int $red, int $green, int $blue): string
    {
        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }
}
