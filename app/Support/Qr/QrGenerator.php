<?php

namespace App\Support\Qr;

use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use GdImage;
use RuntimeException;
use Zxing\QrReader;

final readonly class QrGenerator
{
    public function __construct(private SvgLogoComposer $composer) {}

    /**
     * Vector SVG with the isotipo composed in as real paths. For screen,
     * cartelería and anything that will be scaled.
     */
    public function svg(string $data, int $size = 512): string
    {
        [$innerSize, $margin] = $this->geometry($data, $size);

        $result = (new Builder(
            writer: new SvgWriter,
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $innerSize,
            margin: $margin,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        return $this->composer->compose($result->getString(), (string)config('qr.assets.svg'));
    }

    /**
     * Raster PNG for the shop's thermal printer, exactly $size pixels wide.
     * Pass the printer's real usable width: 384 for 58 mm, 576 for 80 mm.
     */
    public function thermalPng(string $data, ?int $size = null): string
    {
        $size ??= (int)config('qr.thermal.default');

        [$innerSize, $margin] = $this->geometry($data, $size);

        $result = (new Builder(
            writer: new PngWriter,
            writerOptions: [PngWriter::WRITER_OPTION_NUMBER_OF_COLORS => null],
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $innerSize,
            margin: $margin,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        $png = $this->stampLogo($result->getString(), $size);

        // Endroid can validate its own output, but only before the logo goes
        // on, which is exactly the step that can destroy readability. Decode
        // the finished image instead: an unreadable code printed on a run of
        // tickets is a silent, expensive failure.
        $this->assertDecodes($png, $data);

        return $png;
    }

    /**
     * Draws the isotipo over the centre of a rendered QR PNG.
     *
     * Endroid's own logo support is unusable here: it only keeps the alpha
     * channel when the background is transparent, and our asset is a black
     * glyph on transparency, so the whole logo box would flatten to solid
     * black over an opaque background.
     */
    private function stampLogo(string $png, int $size): string
    {
        $canvas = imagecreatefromstring($png);
        $logo = @imagecreatefrompng((string)config('qr.assets.png'));

        if ($canvas === false || $logo === false) {
            throw new RuntimeException('No se pudo componer el logo sobre el QR.');
        }

        // A palette canvas cannot blend the logo's alpha, which leaves the
        // glyph transparent instead of black.
        imagepalettetotruecolor($canvas);

        [$drawnWidth, $drawnHeight] = $this->logoSize($size);
        $padding = (int)round($size * (float)config('qr.logo_ratio') * (float)config('qr.logo_padding'));

        $left = intdiv($size - $drawnWidth, 2);
        $top = intdiv($size - $drawnHeight, 2);

        $this->plate($canvas, $left - $padding, $top - $padding, $drawnWidth + $padding * 2, $drawnHeight + $padding * 2);

        imagealphablending($canvas, true);
        imagecopyresampled(
            $canvas,
            $logo,
            $left,
            $top,
            0,
            0,
            $drawnWidth,
            $drawnHeight,
            imagesx($logo),
            imagesy($logo),
        );

        // The printer wants ink or no ink; an alpha channel would let viewers
        // and drivers show the glyph as see-through rather than black.
        imagesavealpha($canvas, false);

        ob_start();
        imagepng($canvas);

        return (string)ob_get_clean();
    }

    /** Draws the white rounded plate the logo sits on, matching the SVG output. */
    private function plate(GdImage $canvas, int $x, int $y, int $width, int $height): void
    {
        imagealphablending($canvas, false);

        $white = (int)imagecolorallocate($canvas, 255, 255, 255);
        $radius = (int)round(min($width, $height) * 0.08);
        $diameter = $radius * 2;

        imagefilledrectangle($canvas, $x + $radius, $y, $x + $width - $radius, $y + $height, $white);
        imagefilledrectangle($canvas, $x, $y + $radius, $x + $width, $y + $height - $radius, $white);

        foreach ([[$x + $radius, $y + $radius], [$x + $width - $radius, $y + $radius], [$x + $radius, $y + $height - $radius], [$x + $width - $radius, $y + $height - $radius]] as [$cx, $cy]) {
            imagefilledellipse($canvas, $cx, $cy, $diameter, $diameter, $white);
        }
    }

    private function assertDecodes(string $png, string $expected): void
    {
        $decoded = (new QrReader($png, QrReader::SOURCE_TYPE_BLOB))->text();

        if ($decoded !== $expected) {
            throw new RuntimeException('El QR generado no se puede leer: el logo tapa demasiados módulos.');
        }
    }

    /**
     * Picks the inner size and margin that put exactly $targetSize pixels on
     * the outside while keeping the quiet zone at or above the configured
     * number of modules.
     *
     * Endroid treats `size` as the module area and adds `margin` around it, so
     * the outer side is always `size + 2 * margin`. Block size is `floor(size /
     * blockCount)` and does not depend on the margin, which is why the block
     * count can be measured once up front.
     *
     * @return array{int, int}
     */
    private function geometry(string $data, int $targetSize): array
    {
        $quietModules = (int)config('qr.quiet_modules');
        $blockCount = $this->blockCount($data);

        $blockSize = intdiv($targetSize, $blockCount + 2 * $quietModules);

        // The leftover has to split evenly between the two margins. A QR always
        // has an odd block count, so shaving one pixel off the block size flips
        // the leftover's parity and only ever widens the quiet zone.
        if (($targetSize - $blockCount * $blockSize) % 2 !== 0) {
            $blockSize--;
        }

        if ($blockSize < 1) {
            throw new RuntimeException("La URL es demasiado larga para un QR de {$targetSize} px.");
        }

        $innerSize = $blockCount * $blockSize;

        return [$innerSize, intdiv($targetSize - $innerSize, 2)];
    }

    /** How many modules a side, for this payload at error correction level High. */
    private function blockCount(string $data): int
    {
        return (new MatrixFactory)->create(new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
        ))->getBlockCount();
    }

    /**
     * Fits the logo inside a square of `logo_ratio` of the code's side, the
     * same rule SvgLogoComposer applies, so both outputs stay within the same
     * budget even if the asset's aspect ratio changes.
     *
     * @return array{int, int}
     */
    private function logoSize(int $targetSize): array
    {
        $path = (string)config('qr.assets.png');
        $dimensions = @getimagesize($path);

        if ($dimensions === false) {
            throw new RuntimeException("No se pudo leer el logo en {$path}.");
        }

        [$width, $height] = $dimensions;
        $side = $targetSize * (float)config('qr.logo_ratio');
        $scale = min($side / $width, $side / $height);

        return [(int)round($width * $scale), (int)round($height * $scale)];
    }
}
