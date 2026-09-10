<?php

namespace App\Actions\Images;

use App\Support\Og\OgCard;
use App\Support\Og\Typesetter;
use GdImage;

/**
 * Draws a share card and hands back the JPEG bytes.
 *
 * The card is the page in miniature: the book's color on the left with the
 * cover standing on it, the cream page on the right with the title on it. That
 * is not only a likeness -- it is what makes the type safe to set. The title is
 * drawn in the palette's accent, which CoverPalette defines as the cover color
 * walked towards black until it clears 4.5:1 against the paper, so every card
 * in the catalog is legible by construction rather than by inspection.
 *
 * Raw GD rather than spatie/image, which is here only as a transitive
 * dependency of the media library: the two-layer shadow, the auto-shrinking
 * title and the tracked subtitle all need imagettfbbox and a scratch canvas
 * directly, and DownloadPortrait, ColorOfImage and QrGenerator are already
 * written this way.
 *
 * Filing the bytes is somebody else's job.
 */
class ComposeOgCard
{
    public function __invoke(OgCard $card): string
    {
        /* Built here rather than injected: the font path is config, and a
           default constructor argument would have to resolve it before the
           container is up. */
        $type = Typesetter::make();

        $width = $this->dimension('width');
        $height = $this->dimension('height');
        $band = $this->dimension('band');
        $pad = (int)config('og.padding');

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);

        imagefill($canvas, 0, 0, $this->rgb($card->palette->background));

        /* The cream page, to the right of the colored band. */
        imagefilledrectangle($canvas, $band, 0, $width - 1, $height - 1, $this->rgb($this->paper()));

        $this->drawImages($canvas, $card, $band, $pad);
        $this->drawType($canvas, $card, $type, $band, $pad);

        ob_start();
        imagejpeg($canvas, null, (int)config('og.quality'));

        return (string)ob_get_clean();
    }

    /**
     * The left band: up to three covers stacked back to front, or the brand
     * mark when there is nothing to show.
     *
     * @param  int<1, max>  $band  the band gets a canvas of its own, so it has
     *                             to be a width GD will actually allocate
     */
    private function drawImages(GdImage $canvas, OgCard $card, int $band, int $pad): void
    {
        $height = $this->dimension('height');

        /* The band is drawn on a canvas of its own and copied over in one
           piece. The long shadow reaches 120px past the cover on every side,
           and the cover stands close enough to the edge that a shadow drawn
           straight onto the card would lay a band-colored haze down the cream.
           A layer this size clips it for free. */
        $layer = imagecreatetruecolor($band, $height);
        imagealphablending($layer, true);
        imagefill($layer, 0, 0, $this->rgb($card->palette->background));

        $boxX = $pad;
        $boxY = $pad;
        $boxW = $band - $pad * 2;
        $boxH = $height - $pad * 2;

        $decoded = [];

        foreach ($card->images as $bytes) {
            $image = @imagecreatefromstring($bytes);

            if ($image !== false) {
                $decoded[] = $image;
            }
        }

        if ($decoded === []) {
            $this->drawPlate($layer, $card, $boxX, $boxY, $boxW, $boxH);
        } else {
            [$offsetX, $offsetY] = config('og.images.offset');
            $scale = (float)config('og.images.scale');

            /* One cover stands at full height; a stack steps back from the box
               so the ones behind can show past the front one. Shrinking only
               the back ones instead would hide them completely -- the front
               cover already fills the box. */
            $shrink = count($decoded) > 1 ? $scale : 1.0;

            /* Back to front, so the newest cover ends up on top of the pile. */
            foreach (array_reverse($decoded, true) as $index => $image) {

                $this->place(
                    $layer,
                    $card,
                    $image,
                    $boxX + (int)round($index * (int)$offsetX),
                    $boxY + (int)round($index * (int)$offsetY),
                    $boxW,
                    $boxH,
                    $shrink,
                );
            }
        }

        imagecopy($canvas, $layer, 0, 0, 0, 0, $band, $height);
    }

    /**
     * Contain-fit one image in the box and stand it on the page's own shadow.
     */
    private function place(GdImage $canvas, OgCard $card, GdImage $image, int $boxX, int $boxY, int $boxW, int $boxH, float $shrink): void
    {
        $sourceW = imagesx($image);
        $sourceH = imagesy($image);

        $scale = min($boxW / $sourceW, $boxH / $sourceH) * $shrink;
        $width = max(1, (int)round($sourceW * $scale));
        $height = max(1, (int)round($sourceH * $scale));

        $x = $boxX + intdiv($boxW - $width, 2);
        $y = $boxY + intdiv($boxH - $height, 2);

        $this->shadow($canvas, $card, $x, $y, $width, $height);

        imagecopyresampled($canvas, $image, $x, $y, 0, 0, $width, $height, $sourceW, $sourceH);
    }

    /**
     * A book we have no picture of: the 2:3 box it would have filled, with the
     * brand's question mark in it. The shelf draws a coverless book the same
     * way rather than leaving a hole.
     */
    private function drawPlate(GdImage $canvas, OgCard $card, int $boxX, int $boxY, int $boxW, int $boxH): void
    {
        $height = $boxH;
        $width = (int)round($height * 2 / 3);

        if ($width > $boxW) {
            $width = $boxW;
            $height = (int)round($width * 3 / 2);
        }

        $x = $boxX + intdiv($boxW - $width, 2);
        $y = $boxY + intdiv($boxH - $height, 2);

        $this->shadow($canvas, $card, $x, $y, $width, $height);

        imagefilledrectangle($canvas, $x, $y, $x + $width, $y + $height, $this->rgb($card->palette->background));
        imagesetthickness($canvas, 2);
        imagerectangle($canvas, $x, $y, $x + $width, $y + $height, $this->rgb($this->blend($card->palette->foreground, $card->palette->background, 0.55)));
        imagesetthickness($canvas, 1);

        $mark = @imagecreatefrompng((string)config('og.assets.isotipo'));

        if ($mark === false) {
            return;
        }

        imagepalettetotruecolor($mark);
        imagealphablending($mark, false);
        imagesavealpha($mark, true);

        /* The glyph is black on transparent, so colorizing moves it onto the
           band's foreground without touching its alpha. */
        [$r, $g, $b] = $this->channels($card->palette->foreground);
        imagefilter($mark, IMG_FILTER_COLORIZE, $r, $g, $b);

        $markW = (int)round($width * 0.34);
        $markH = (int)round($markW * imagesy($mark) / imagesx($mark));

        imagecopyresampled(
            $canvas,
            $mark,
            $x + intdiv($width - $markW, 2),
            $y + intdiv($height - $markH, 2),
            0,
            0,
            $markW,
            $markH,
            imagesx($mark),
            imagesy($mark),
        );
    }

    /**
     * The page's two-layer ink shadow.
     *
     * Each layer is blurred at a fraction of its size and scaled back up. GD's
     * gaussian is a fixed 3x3 kernel applied over and over, so a 60px radius at
     * full size is a hundred passes over three quarters of a million pixels --
     * seconds of work that nothing in a chat thumbnail could tell from this.
     */
    private function shadow(GdImage $canvas, OgCard $card, int $x, int $y, int $width, int $height): void
    {
        foreach (config('og.shadow') as $layer) {
            [$offsetY, $radius, $opacity, $down] = $layer;

            $margin = (int)$radius * 2;
            $fullW = max(1, $width + $margin * 2);
            $fullH = max(1, $height + $margin * 2);

            $smallW = max(4, intdiv($fullW, (int)$down));
            $smallH = max(4, intdiv($fullH, (int)$down));

            $scratch = imagecreatetruecolor($smallW, $smallH);
            imagefill($scratch, 0, 0, $this->rgb($card->palette->background));
            imagefilledrectangle(
                $scratch,
                intdiv($margin, (int)$down),
                intdiv($margin, (int)$down),
                $smallW - intdiv($margin, (int)$down),
                $smallH - intdiv($margin, (int)$down),
                $this->rgb((string)config('site.palette.ink')),
            );

            $passes = max(1, (int)round((($radius / (int)$down) ** 2) / 3));

            for ($pass = 0; $pass < $passes; $pass++) {
                imagefilter($scratch, IMG_FILTER_GAUSSIAN_BLUR);
            }

            $up = imagecreatetruecolor($fullW, $fullH);
            imagecopyresampled($up, $scratch, 0, 0, 0, 0, $fullW, $fullH, $smallW, $smallH);

            imagecopymerge(
                $canvas,
                $up,
                $x - $margin,
                $y - $margin + (int)$offsetY,
                0,
                0,
                $fullW,
                $fullH,
                (int)round((float)$opacity * 100),
            );
        }
    }

    /**
     * The right column: the title, a rule, the subtitle, and the wordmark in
     * the bottom corner. The three type blocks are centered as one, so a
     * one-line title and a three-line one both sit on the same optical axis.
     */
    private function drawType(GdImage $canvas, OgCard $card, Typesetter $type, int $band, int $pad): void
    {
        $x = $band + $pad;
        $columnWidth = (int)config('og.width') - $x - $pad;

        $wordmarkHeight = $this->drawWordmark($canvas, $columnWidth, $x, $pad);

        $title = $type->fit(
            $card->title,
            $columnWidth,
            (int)config('og.title.size'),
            (int)config('og.title.min'),
            (int)config('og.title.step'),
            (int)config('og.title.lines'),
        );

        $lineHeight = $type->lineHeight($title['size']);
        $capHeight = $type->capHeight($title['size']);
        $subtitleSize = (int)config('og.subtitle.size');
        $hasSubtitle = $card->subtitle !== '';

        $blockHeight = (count($title['lines']) - 1) * $lineHeight + $capHeight;

        if ($hasSubtitle) {
            $blockHeight += (int)config('og.rule.gap')
                + (int)config('og.rule.width')
                + (int)config('og.subtitle_gap')
                + $type->capHeight($subtitleSize);
        }

        $available = (int)config('og.height') - $pad * 2 - $wordmarkHeight;
        $top = $pad + intdiv($available - $blockHeight, 2);

        $accent = $this->rgb($card->palette->accent);

        foreach ($title['lines'] as $index => $line) {
            $type->draw($canvas, $line, $title['size'], $x, $top + $capHeight + $index * $lineHeight, $accent);
        }

        if (! $hasSubtitle) {
            return;
        }

        $ruleY = $top + (count($title['lines']) - 1) * $lineHeight + $capHeight + (int)config('og.rule.gap');

        imagefilledrectangle(
            $canvas,
            $x,
            $ruleY,
            $x + (int)config('og.rule.length'),
            $ruleY + (int)config('og.rule.width') - 1,
            $this->rgb($this->blend($card->palette->accent, $this->paper(), 0.45)),
        );

        $type->drawTracked(
            $canvas,
            $type->fitTracked(
                mb_strtoupper($card->subtitle),
                $subtitleSize,
                (float)config('og.subtitle.tracking'),
                $columnWidth,
            ),
            $subtitleSize,
            $x,
            $ruleY + (int)config('og.rule.width') + (int)config('og.subtitle_gap') + $type->capHeight($subtitleSize),
            $this->rgb($this->blend((string)config('site.palette.ink'), $this->paper(), 0.25)),
            (float)config('og.subtitle.tracking'),
        );
    }

    /**
     * The wordmark, bottom-right on the cream. It carries its own brand colors
     * -- black, mint and magenta -- so it is pasted rather than recolored, the
     * same rule the site header follows.
     *
     * @return int the room it took, so the type above can be centered in what is left
     */
    private function drawWordmark(GdImage $canvas, int $columnWidth, int $x, int $pad): int
    {
        $mark = @imagecreatefrompng((string)config('og.assets.wordmark'));

        if ($mark === false) {
            return 0;
        }

        imagealphablending($mark, false);
        imagesavealpha($mark, true);

        $width = min((int)config('og.wordmark_width'), $columnWidth);
        $height = (int)round($width * imagesy($mark) / imagesx($mark));

        imagealphablending($canvas, true);
        imagecopyresampled(
            $canvas,
            $mark,
            $x + $columnWidth - $width,
            (int)config('og.height') - $pad - $height,
            0,
            0,
            $width,
            $height,
            imagesx($mark),
            imagesy($mark),
        );

        return $height + $pad;
    }

    private function paper(): string
    {
        return (string)config('site.palette.paper');
    }

    /**
     * A pixel dimension out of config/og.php, floored at one.
     *
     * `imagecreatetruecolor()` refuses anything smaller and answers false, and
     * every GD call after it then warns against a non-image -- so a zero left
     * in the config would surface as a wall of warnings and an empty card
     * rather than as the one line that is wrong.
     *
     * @return int<1, max>
     */
    private function dimension(string $key): int
    {
        return max(1, (int)config("og.{$key}"));
    }

    /**
     * Mix a color towards another, since CoverPalette::foregroundFaded() speaks
     * CSS color-mix() and GD cannot read it.
     */
    private function blend(string $color, string $towards, float $amount): string
    {
        [$r1, $g1, $b1] = $this->channels($color);
        [$r2, $g2, $b2] = $this->channels($towards);

        return sprintf(
            '#%02x%02x%02x',
            (int)round($r1 + ($r2 - $r1) * $amount),
            (int)round($g1 + ($g2 - $g1) * $amount),
            (int)round($b1 + ($b2 - $b1) * $amount),
        );
    }

    /**
     * The three channels of a #rrggbb string.
     *
     * Clamped, because the colors reaching here are config values and a stored
     * `cover_color` -- not literals. A short or malformed string makes `hexdec`
     * read whatever `substr` could give it, and an out-of-range channel is
     * accepted silently by the bit-packing in `rgb()` and comes back as a
     * different color entirely.
     *
     * @return array{int<0, 255>, int<0, 255>, int<0, 255>}
     */
    private function channels(string $color): array
    {
        $hex = ltrim($color, '#');

        return [
            $this->channel(substr($hex, 0, 2)),
            $this->channel(substr($hex, 2, 2)),
            $this->channel(substr($hex, 4, 2)),
        ];
    }

    /**
     * @return int<0, 255>
     */
    private function channel(string $hex): int
    {
        return max(0, min(255, (int)hexdec($hex)));
    }

    private function rgb(string $color): int
    {
        [$r, $g, $b] = $this->channels($color);

        return ($r << 16) | ($g << 8) | $b;
    }
}
