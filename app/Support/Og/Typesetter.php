<?php

namespace App\Support\Og;

use GdImage;
use RuntimeException;

/**
 * Everything the share card needs from FreeType.
 *
 * GD's text API is built around a pen rather than a box, and three of its
 * habits will quietly ruin a layout:
 *
 * - `imagettftext()` takes the *baseline* of the first line as its y, not a top
 *   edge, and its x is the pen origin rather than the visual left edge.
 * - `imagettfbbox()` returns eight numbers around an origin at (0,0) on the
 *   baseline, with y negative above it -- so the height above the baseline is
 *   `-$box[7]`, not `$box[1]`.
 * - it does honour "\n", but with FreeType's own leading, which cannot be set.
 *   Every line here is therefore drawn on a baseline this class worked out.
 *
 * spatie/image's own wrapText() is not usable for any of this: it seeds the
 * result with an empty string and concatenates " {$word}", so every line comes
 * back with a leading space; it measures with `$box[2]` alone, ignoring the
 * left bearing; and it never breaks a word longer than the box.
 */
final readonly class Typesetter
{
    public function __construct(private string $font)
    {
        if (! is_file($this->font)) {
            /* Loud rather than a blank card: a deploy that skipped the font is
               a deploy where every share preview is silently empty. */
            throw new RuntimeException("No se encontró la tipografía de las tarjetas en [{$this->font}].");
        }
    }

    public static function make(): self
    {
        return new self((string)config('og.assets.font'));
    }

    /**
     * Wrap at the largest size that fits, stepping down until it does.
     *
     * Because wrapping is measured rather than guessed, a size whose wrap comes
     * back within the line budget is a size that fits -- there is no second
     * check to forget. At the floor the text is cut instead.
     *
     * @return array{lines: list<string>, size: int}
     */
    public function fit(string $text, int $width, int $size, int $min, int $step, int $maxLines): array
    {
        for ($candidate = $size; $candidate >= $min; $candidate -= $step) {
            $lines = $this->wrap($text, $candidate, $width);

            if (count($lines) <= $maxLines) {
                return ['lines' => $lines, 'size' => $candidate];
            }
        }

        $lines = array_slice($this->wrap($text, $min, $width), 0, $maxLines);

        /* Text that is only whitespace wraps to nothing, and there is no last
           line to cut. Indexing `count($lines) - 1` there writes key -1 and
           hands back something that is no longer a list. */
        if ($lines === []) {
            return ['lines' => [], 'size' => $min];
        }

        $last = array_key_last($lines);
        $lines[$last] = $this->ellipsize($lines[$last], $min, $width);

        return ['lines' => $lines, 'size' => $min];
    }

    /**
     * Greedy wrap, breaking inside a word that cannot fit on a line of its own
     * -- a long compound or a pasted URL would otherwise run off the card.
     *
     * @return list<string>
     */
    public function wrap(string $text, int $size, int $width): array
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            if ($word === '') {
                continue;
            }

            $candidate = $current === '' ? $word : "{$current} {$word}";

            if ($this->advance($candidate, $size) <= $width) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            if ($this->advance($word, $size) <= $width) {
                $current = $word;

                continue;
            }

            foreach ($this->breakWord($word, $size, $width) as $piece) {
                if ($piece !== '') {
                    $lines[] = $piece;
                }
            }

            $current = (string)array_pop($lines);
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * The width a string will occupy, from the left bearing to the right
     * advance, so a line beginning with a V or an A measures honestly.
     */
    public function advance(string $text, int $size): int
    {
        $box = imagettfbbox($size, 0, $this->font, $text);

        return $box === false ? 0 : $box[2] - $box[0];
    }

    /**
     * The leading, measured once against a string carrying both an accent and a
     * descender and then reused for every line.
     *
     * Spanish titles put accents above the cap line, so a leading measured
     * without one clips them against the line above; and taking each line's
     * height from its own box springs the block open wherever a line happens to
     * have no descender.
     */
    public function lineHeight(int $size): int
    {
        $box = imagettfbbox($size, 0, $this->font, 'ÁQgjyÑ');

        if ($box === false) {
            return (int)round($size * (float)config('og.line_height'));
        }

        return (int)round((-$box[7] + $box[1]) * (float)config('og.line_height'));
    }

    /**
     * How far a line reaches above its own baseline.
     */
    public function capHeight(int $size): int
    {
        $box = imagettfbbox($size, 0, $this->font, 'ÁQÑ');

        return $box === false ? $size : (int)round(-$box[7]);
    }

    /**
     * Draw one line, left-aligned on $x by its visual edge rather than by the
     * pen origin, sitting on the baseline $baseline.
     */
    public function draw(GdImage $canvas, string $text, int $size, int $x, int $baseline, int $color): void
    {
        $box = imagettfbbox($size, 0, $this->font, $text);
        $bearing = $box === false ? 0 : $box[0];

        imagettftext($canvas, $size, 0, $x - $bearing, $baseline, $color, $this->font, $text);
    }

    /**
     * The same, glyph by glyph with letter-spacing.
     *
     * FreeType has no tracking, and the site sets every kicker wide -- so the
     * card's subtitle, which is a kicker, is walked one character at a time.
     */
    public function drawTracked(GdImage $canvas, string $text, int $size, int $x, int $baseline, int $color, float $tracking): void
    {
        $step = (int)round($size * $tracking);

        foreach (mb_str_split($text) as $glyph) {
            $this->draw($canvas, $glyph, $size, $x, $baseline, $color);
            $x += $this->advance($glyph, $size) + $step;
        }
    }

    /**
     * The width the tracked form of a string will occupy.
     */
    public function trackedAdvance(string $text, int $size, float $tracking): int
    {
        $glyphs = mb_str_split($text);
        $step = (int)round($size * $tracking);
        $width = 0;

        foreach ($glyphs as $glyph) {
            $width += $this->advance($glyph, $size) + $step;
        }

        return max(0, $width - $step);
    }

    /**
     * Cut a tracked line down to the width it has.
     *
     * The subtitle is one line by design -- it is a kicker, not a sentence --
     * so a long author list is trimmed rather than wrapped. Without this a
     * three-author book runs its credit straight off the edge of the card.
     */
    public function fitTracked(string $text, int $size, float $tracking, int $width): string
    {
        if ($this->trackedAdvance($text, $size, $tracking) <= $width) {
            return $text;
        }

        $characters = mb_str_split($text);

        while ($characters !== [] && $this->trackedAdvance(implode('', $characters) . '…', $size, $tracking) > $width) {
            array_pop($characters);
        }

        return rtrim(implode('', $characters), ' ,;·-') . '…';
    }

    /**
     * @return list<string>
     */
    private function breakWord(string $word, int $size, int $width): array
    {
        $pieces = [];
        $current = '';

        foreach (mb_str_split($word) as $character) {
            if ($current !== '' && $this->advance($current . $character, $size) > $width) {
                $pieces[] = $current;
                $current = '';
            }

            $current .= $character;
        }

        $pieces[] = $current;

        return $pieces;
    }

    private function ellipsize(string $line, int $size, int $width): string
    {
        $characters = mb_str_split($line);

        while ($characters !== [] && $this->advance(implode('', $characters) . '…', $size) > $width) {
            array_pop($characters);
        }

        return rtrim(implode('', $characters)) . '…';
    }
}
