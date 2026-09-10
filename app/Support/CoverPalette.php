<?php

namespace App\Support;

/**
 * The colors a book's page is painted with, derived from its cover.
 *
 * The design is one flat color taken off the cover plus cream and ink, so the
 * only thing that varies from book to book is that color -- and the two
 * decisions that follow from it: what to write on top of it, and how dark it
 * has to get before it reads as a link on the cream.
 *
 * Both decisions are made by contrast ratio rather than by a lightness
 * threshold, because the averaged colors the covers produce land all over the
 * place: a washed pink for one book, a near-black brown for the next.
 *
 * The fixed colors and the two thresholds are `site.palette` in config.
 */
final readonly class CoverPalette
{
    private function __construct(
        /** The flat color behind the hero, the top bar and the publisher band. */
        public string $background,
        /** Cream or ink, whichever can be read over the background. */
        public string $foreground,
        /** The background darkened until it can be read over the cream page. */
        public string $accent,
    ) {}

    public static function fromCover(?string $color): self
    {
        $background = self::normalize($color) ?? self::color('fallback');

        return new self(
            background: $background,
            foreground: self::mostReadableOver($background),
            accent: self::darkenUntilReadable($background),
        );
    }

    /**
     * A color to draw rules and muted text with, over the background.
     */
    public function foregroundFaded(float $opacity = 0.45): string
    {
        return sprintf('color-mix(in srgb, %s %d%%, transparent)', $this->foreground, (int)round($opacity * 100));
    }

    /**
     * How the wordmark should be blended over this background: `multiply`
     * where its own colors cannot be read on it, `normal` everywhere else.
     *
     * The wordmark carries its own brand colors and is never recolored, and
     * the "LA" in it is the brand mint -- which is also the house cover color,
     * so on the shelf and on La Cupida those two letters are painted in the
     * background they sit on and there is nothing to see. Multiply is the way
     * out that costs no weight: it can only darken, so the mint over mint
     * comes back as a deeper green, the black stays black, and no glyph gains
     * the pixel an outline would have given every one of them.
     *
     * Decided by contrast, like the other two, so the near-mint cover of a
     * real book is caught as well as the house color itself, and left at
     * `normal` everywhere the mint reads -- multiply is not free, it takes the
     * magenta down with it. `site.palette.wordmark_min_contrast` is where the
     * line is drawn and why.
     */
    public function wordmarkBlend(): string
    {
        $minContrast = (float)config('site.palette.wordmark_min_contrast');

        return self::contrast($this->background, self::color('wordmark')) < $minContrast
            ? 'multiply'
            : 'normal';
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
        return self::contrast($background, self::color('cream')) >= self::contrast($background, self::color('ink'))
            ? self::color('cream')
            : self::color('ink');
    }

    /**
     * Walk the color towards black until it stands out on the cream page.
     *
     * Scaling all three channels by the same factor keeps the hue, so a pale
     * pink cover yields a deep rose rather than a generic dark gray. The loop
     * always terminates: every step is a strict darkening, and black clears the
     * threshold against cream.
     */
    private static function darkenUntilReadable(string $color): string
    {
        [$red, $green, $blue] = self::channels($color);

        $paper = self::color('paper');
        $minContrast = (float)config('site.palette.min_contrast');
        $step = (float)config('site.palette.darken_step');

        while (self::contrast(self::hex($red, $green, $blue), $paper) < $minContrast) {
            $darker = array_map(fn(int $channel): int => (int)floor($channel * $step), [$red, $green, $blue]);

            if ($darker === [$red, $green, $blue]) {
                return self::color('ink');
            }

            [$red, $green, $blue] = $darker;
        }

        return self::hex($red, $green, $blue);
    }

    /**
     * WCAG 2.1 relative contrast between two colors, from 1 to 21.
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

    /**
     * One of the fixed colors the site is painted with, from config/site.php.
     */
    private static function color(string $name): string
    {
        return (string)config("site.palette.{$name}");
    }
}
