<?php

namespace App\Support\Qr;

use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Draws a vector logo over the centre of an already generated QR SVG.
 *
 * Endroid can embed a logo itself, but only the PNG writer supports punching
 * the modules out from behind it. The SVG writer inlines the logo as a base64
 * <image>, which both rasterises it and lets the modules show through, so the
 * logo is composed onto the DOM here instead.
 */
final class SvgLogoComposer
{
    private const string NS = 'http://www.w3.org/2000/svg';

    public function compose(string $qrSvg, string $logoSvgPath): string
    {
        $qr = $this->load($qrSvg);
        $logo = $this->load($this->read($logoSvgPath));

        [$qrX, $qrY, $qrWidth, $qrHeight] = $this->viewBox($qr);
        [$logoX, $logoY, $logoWidth, $logoHeight] = $this->viewBox($logo);

        $side = $qrWidth * (float)config('qr.logo_ratio');
        $padding = $side * (float)config('qr.logo_padding');

        // The glyph is taller than it is wide, so fitting it inside a square
        // means the height is what binds. Deriving the scale keeps that true
        // for any replacement asset.
        $scale = min($side / $logoWidth, $side / $logoHeight);
        $drawnWidth = $logoWidth * $scale;
        $drawnHeight = $logoHeight * $scale;

        $centerX = $qrX + $qrWidth / 2;
        $centerY = $qrY + $qrHeight / 2;

        $root = $this->root($qr);

        $root->appendChild($this->punchout(
            $qr,
            $centerX,
            $centerY,
            $drawnWidth + $padding * 2,
            $drawnHeight + $padding * 2,
        ));

        $root->appendChild($this->logoGroup(
            $qr,
            $logo,
            $centerX - $drawnWidth / 2,
            $centerY - $drawnHeight / 2,
            $scale,
            $logoX,
            $logoY,
        ));

        // saveXML() with no argument serialises the whole document and prepends
        // an XML declaration, which would be echoed inline by the Blade view.
        return (string)$qr->saveXML($root);
    }

    private function punchout(DOMDocument $document, float $centerX, float $centerY, float $width, float $height): DOMElement
    {
        $rect = $document->createElementNS(self::NS, 'rect');
        $rect->setAttribute('x', $this->number($centerX - $width / 2));
        $rect->setAttribute('y', $this->number($centerY - $height / 2));
        $rect->setAttribute('width', $this->number($width));
        $rect->setAttribute('height', $this->number($height));
        $rect->setAttribute('rx', $this->number(min($width, $height) * 0.08));
        $rect->setAttribute('fill', '#FFFFFF');

        return $rect;
    }

    private function logoGroup(
        DOMDocument $document,
        DOMDocument $logo,
        float $left,
        float $top,
        float $scale,
        float $logoX,
        float $logoY,
    ): DOMElement {
        $group = $document->createElementNS(self::NS, 'g');
        $group->setAttribute('transform', sprintf(
            'translate(%s,%s) scale(%s) translate(%s,%s)',
            $this->number($left),
            $this->number($top),
            $this->number($scale),
            $this->number(-$logoX),
            $this->number(-$logoY),
        ));

        foreach (iterator_to_array($this->root($logo)->childNodes) as $node) {
            $group->appendChild($document->importNode($node, true));
        }

        return $group;
    }

    private function read(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("No se pudo leer el logo en {$path}.");
        }

        return $contents;
    }

    private function load(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;

        if (! @$document->loadXML($xml)) {
            throw new RuntimeException('SVG inválido.');
        }

        return $document;
    }

    private function root(DOMDocument $document): DOMElement
    {
        $root = $document->documentElement;

        if (! $root instanceof DOMElement) {
            throw new RuntimeException('El SVG no tiene elemento raíz.');
        }

        return $root;
    }

    /** @return array{float, float, float, float} */
    private function viewBox(DOMDocument $document): array
    {
        $raw = $this->root($document)->getAttribute('viewBox');

        if ($raw === '') {
            throw new RuntimeException('El SVG no declara viewBox.');
        }

        $parts = preg_split('/[\s,]+/', trim($raw));

        if ($parts === false || count($parts) !== 4) {
            throw new RuntimeException("viewBox malformado: {$raw}");
        }

        [$x, $y, $width, $height] = array_map(floatval(...), $parts);

        return [$x, $y, $width, $height];
    }

    /**
     * Formats a float without scientific notation or a locale decimal comma,
     * either of which would silently corrupt a transform attribute.
     */
    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
