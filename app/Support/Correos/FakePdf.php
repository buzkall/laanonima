<?php

namespace App\Support\Correos;

/**
 * A one-page PDF, built by hand.
 *
 * The offline Correos fake has to hand the browser something a PDF viewer will
 * actually open -- a download that fails to render proves nothing about the
 * label path -- and the project has no PDF library. This is the smallest file
 * that satisfies a reader: catalog, pages, one page, one Helvetica text run,
 * and an xref table whose offsets are measured rather than guessed.
 */
class FakePdf
{
    private const int PAGE_WIDTH = 595;
    private const int PAGE_HEIGHT = 842;

    /**
     * @param  list<string>  $lines  One line of text per entry, top to bottom.
     */
    public static function make(array $lines): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . ']'
                . ' /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            self::stream(self::content($lines)),
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        return $pdf . self::xref($offsets, strlen($pdf));
    }

    /**
     * @param  list<string>  $lines
     */
    private static function content(array $lines): string
    {
        $text = "BT\n/F1 14 Tf\n18 TL\n56 " . (self::PAGE_HEIGHT - 80) . " Td\n";

        foreach ($lines as $line) {
            $text .= '(' . self::escape($line) . ")Tj\nT*\n";
        }

        return $text . "ET\n";
    }

    private static function stream(string $content): string
    {
        return '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';
    }

    /**
     * The cross-reference table is the one part a reader will not forgive: each
     * entry is exactly 20 bytes and points at the byte its object starts on.
     *
     * @param  list<int>  $offsets
     */
    private static function xref(array $offsets, int $startxref): string
    {
        $count = count($offsets) + 1;

        $xref = "xref\n0 {$count}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $xref .= sprintf("%010d 00000 n \n", $offset);
        }

        return $xref . "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$startxref}\n%%EOF\n";
    }

    /**
     * Only the three characters that carry meaning inside a PDF string, and
     * anything outside ASCII, which Helvetica's default encoding would render
     * as mojibake -- "Anónima" included.
     */
    private static function escape(string $text): string
    {
        $ascii = (string)iconv('UTF-8', 'ASCII//TRANSLIT', $text);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }
}
