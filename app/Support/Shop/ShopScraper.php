<?php

namespace App\Support\Shop;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads the shop's live site, laanonimalibreria.com.
 *
 * Deliberately outside App\Support\Cupida: two things read the shop now -- the
 * Cupida pool and the subject tree the real catalog is classified with -- and
 * the second of those outlives the first. Everything under Cupida is a stand-in
 * for a catalog we do not own yet and gets deleted when we do.
 *
 * Two things have to be got right before any parsing happens, and both fail
 * quietly rather than loudly if they are not.
 *
 * The first is the challenge. A cold request is answered with a two-line page
 * that sets a cookie in JavaScript and reloads itself; nothing on the site
 * works until that cookie comes back, and no combination of headers gets past
 * it, so the value is read out of that first body and kept for the run.
 *
 * The second is the encoding. Every page declares ISO-8859-1 and means it, so
 * a response that is not converted before it is parsed turns every accented
 * character into mojibake -- which does not throw, does not fail a request, and
 * only shows up as "Ficci\u{f3}n" in a JSON file three steps later.
 *
 * This class only fetches and parses. What is worth keeping is decided by
 * ScrapeCupidaCatalog.
 */
class ShopScraper
{
    private ?string $challenge = null;

    /**
     * The shop's subject tree, one level.
     *
     * `/libros/` lists the 36 top-level THEMA codes; each of those lists its
     * own children, which is where the codes a reader would recognize live
     * ("Fantasía" is FM, under F "Ficción y temas afines").
     *
     * @return array<string, string> code => heading
     */
    public function subjects(string $path = 'libros/'): array
    {
        $document = $this->fetch($path);
        $found = [];

        foreach ($this->query($document, '//a[contains(@href, "/materia/")]') as $link) {
            $href = $link->getAttribute('href');

            if (preg_match('#/materia/([A-Z0-9]+)/#', $href, $matches) !== 1) {
                continue;
            }

            $heading = $this->text($link);

            if (filled($heading)) {
                $found[$matches[1]] = $heading;
            }
        }

        return $found;
    }

    /**
     * The children of one subject, e.g. F => FB, FC, FD ...
     *
     * @return array<string, string> code => heading
     */
    public function childrenOf(string $code, string $slug): array
    {
        return array_filter(
            $this->subjects("materia/{$code}/{$slug}/"),
            fn(string $heading, string $child): bool => $child !== $code && str_starts_with($child, $code),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * One page of a listing -- a subject, a curated shelf, anything built out
     * of the shop's `div.libro` cards.
     *
     * The listing carries everything the pool needs except the synopsis, which
     * is why `--details` is a separate pass: 36 books arrive in one request
     * here and cost 36 requests there.
     *
     * `next` is the shop's own "next page" link rather than a page number this
     * class made up. The pagination is a module id and a subject code
     * (`?pag=modulo&IdModulo=40&codmateria=FM&p=2`), none of which is
     * reconstructable from the pretty URL, so the only reliable way to page a
     * listing is to read the link the shop printed and follow it.
     *
     * @return array{total: int, books: array<int, array<string, mixed>>, next: string|null}
     */
    public function listing(string $path): array
    {
        $document = $this->fetch($path);

        $books = [];

        foreach ($this->query($document, '//div[' . $this->hasClass('libro') . ']') as $card) {
            $book = $this->cardToBook($card);

            if ($book !== null) {
                $books[$book['ean']] = $book;
            }
        }

        return [
            'total' => $this->resultCount($document),
            'books' => array_values($books),
            'next'  => $this->nextPage($document, $path),
        ];
    }

    /**
     * One book's own page: the synopsis and the publisher, plus the subject
     * codes it is actually filed under, which the listing does not carry.
     *
     * @return array<string, mixed>|null
     */
    public function book(string $ean, string $slug): ?array
    {
        $document = $this->fetch("libros/{$ean}/{$slug}/");
        $xpath = new DOMXPath($document);

        $synopsis = $this->text($this->first($xpath, '//div[contains(@class, "resena")]'));

        if (blank($synopsis)) {
            return null;
        }

        $facts = $this->text($this->first($xpath, '//div[contains(@class, "text-left") and contains(@class, "small")]'));

        $subjects = [];

        foreach ($this->query($document, '//a[contains(@href, "/materia/")]') as $link) {
            if (preg_match('#/materia/([A-Z0-9]+)/#', $link->getAttribute('href'), $matches) === 1) {
                $subjects[] = $matches[1];
            }
        }

        return [
            'synopsis'  => $synopsis,
            'publisher' => $this->text($this->first($xpath, '//a[contains(@href, "/editoriales/")]')) ?: null,
            'subjects'  => array_values(array_unique($subjects)),
            'pages'     => $this->fact($facts, 'Nº Páginas'),
            'year'      => $this->fact($facts, 'Edición'),
        ];
    }

    /**
     * The URL of a cover at a given width. The shop resizes on demand, so this
     * is the one image URL that is guaranteed to exist for a stocked EAN.
     */
    public function coverUrl(string $ean, int $width = 400): string
    {
        return $this->url("imagen.php?ean={$ean}&ancho={$width}");
    }

    public function url(string $path): string
    {
        return rtrim((string)config('cupida.scrape.base_url'), '/') . '/' . ltrim($path, '/');
    }

    /**
     * One `div.libro` card off a listing page.
     *
     * @return array<string, mixed>|null
     */
    private function cardToBook(DOMElement $card): ?array
    {
        $xpath = new DOMXPath($card->ownerDocument);

        $link = $this->first($xpath, './/a[contains(@href, "libros/")]', $card);

        if (! $link instanceof DOMElement || preg_match('#libros/(\d+)/([^/"]+)/#', $link->getAttribute('href'), $matches) !== 1) {
            return null;
        }

        $title = $this->text($this->first($xpath, './/*[contains(@class, "titulo")]', $card));

        if (blank($title)) {
            return null;
        }

        return [
            'ean'         => $matches[1],
            'slug'        => $matches[2],
            'title'       => $title,
            'author'      => $this->text($this->first($xpath, './/*[contains(@class, "autor")]', $card)) ?: null,
            'price_cents' => $this->priceInCents($this->text($this->first($xpath, './/*[contains(@class, "btn-compra")]', $card))),
            'available'   => str_contains($this->text($this->first($xpath, './/*[contains(@class, "existencias")]', $card)), 'Disponible'),
        ];
    }

    /**
     * "Mostrando del 1 al 36 de 301 resultados" -- how much of a subject the
     * shop actually stocks, which is what a card's note line reports.
     */
    private function resultCount(DOMDocument $document): int
    {
        $bar = $this->text($this->first(new DOMXPath($document), '//div[contains(@class, "barra-paginacion")]'));

        return preg_match('/de\s+([\d.]+)\s+resultados/u', $bar, $matches) === 1
            ? (int)str_replace('.', '', $matches[1])
            : 0;
    }

    /**
     * A "<b>Label</b>: value" line off the book page's fact block.
     */
    private function fact(string $facts, string $label): ?int
    {
        $pattern = '/' . preg_quote($label, '/') . '\s*:\s*(\d+)/u';

        return preg_match($pattern, $facts, $matches) === 1 ? (int)$matches[1] : null;
    }

    /**
     * "24,00 EUR" or "1.234,56 EUR", in cents.
     *
     * The separator that counts is the last one: the shop prints Spanish
     * numbers, so a book over a thousand euros carries a thousands dot as well
     * as the decimal comma, and a pattern anchored on the first separator reads
     * "1.234,56" as one euro twenty-three. Nothing throws -- the price is
     * simply wrong in a committed JSON file, which is the same silent shape as
     * the two encoding traps above.
     */
    private function priceInCents(string $price): ?int
    {
        if (preg_match('/(\d[\d.,]*)[.,](\d{2})(?!\d)/', $price, $matches) !== 1) {
            return null;
        }

        return (int)str_replace(['.', ','], '', $matches[1]) * 100 + (int)$matches[2];
    }

    /**
     * The link to the page after this one, resolved against the path we are on.
     *
     * The pagination prints every page as its own anchor with the current one
     * as plain text, so "next" is the first anchor whose `p` is higher than the
     * current page rather than anything the markup labels as next.
     */
    private function nextPage(DOMDocument $document, string $path): ?string
    {
        $xpath = new DOMXPath($document);

        $current = (int)$this->text($this->first($xpath, '//*[@id="ew_pagina_actual"]')) ?: 1;

        foreach ($this->query($document, '//div[' . $this->hasClass('paginacion') . ']//a[contains(@href, "p=")]') as $link) {
            $href = $link->getAttribute('href');

            if (preg_match('/[?&]p=(\d+)/', $href, $matches) === 1 && (int)$matches[1] === $current + 1) {
                /* The hrefs are bare query strings, relative to the directory
                   we asked for -- "?pag=modulo&..." under materia/FM/fantasia/. */
                return str_starts_with($href, '?')
                    ? $this->withoutQuery($path) . $href
                    : $href;
            }
        }

        return null;
    }

    private function withoutQuery(string $path): string
    {
        return strtok($path, '?') ?: $path;
    }

    /**
     * XPath for a real class attribute rather than a substring of one: without
     * the padding, "libro" also matches "libros", "librosmateria" and half the
     * shop's layout classes.
     */
    private function hasClass(string $class): string
    {
        return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
    }

    /**
     * @return array<int, DOMElement>
     */
    private function query(DOMDocument $document, string $expression): array
    {
        $nodes = new DOMXPath($document)->query($expression);
        $found = [];

        foreach ($nodes ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $found[] = $node;
            }
        }

        return $found;
    }

    private function first(DOMXPath $xpath, string $expression, ?DOMNode $context = null): ?DOMElement
    {
        $nodes = $xpath->query($expression, $context);
        $first = $nodes === false ? null : $nodes->item(0);

        return $first instanceof DOMElement ? $first : null;
    }

    private function text(?DOMNode $node): string
    {
        if (! $node instanceof DOMNode) {
            return '';
        }

        return trim((string)preg_replace('/\s+/u', ' ', $node->textContent));
    }

    /**
     * Fetch a page, converted and parsed, with the challenge already solved.
     */
    private function fetch(string $path): DOMDocument
    {
        $response = $this->request()->get($this->url($path));

        if (! $response->successful()) {
            throw new RuntimeException("The shop answered {$response->status()} for {$path}.");
        }

        $html = $this->toUtf8($response->body());

        /* A challenge can be served in the middle of a run, not only first. */
        if ($this->looksLikeChallenge($html)) {
            $this->challenge = $this->challengeFrom($html);
            $html = $this->toUtf8($this->request()->get($this->url($path))->body());
        }

        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($this->asAscii($html));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    /**
     * The pages are ISO-8859-1 and say so. Convert before parsing, never after:
     * DOMDocument will otherwise take the bytes at face value and there is no
     * way back to the accents once they are gone.
     */
    private function toUtf8(string $html): string
    {
        return mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
    }

    /**
     * Hand DOMDocument pure ASCII, with every accent as a numeric entity.
     *
     * Converting the bytes to UTF-8 is not enough on its own. The pages still
     * carry `<meta charset="ISO-8859-1">`, DOMDocument believes it over
     * anything passed in, and decodes the already-converted string a second
     * time -- so "Ficción" arrives as "FicciÃ³n". Prefixing an XML declaration
     * is the usual trick and loses to the meta tag here.
     *
     * Entities sidestep the argument entirely: there is nothing left above
     * 0x7F for a charset to be wrong about, and textContent hands back proper
     * UTF-8 on the way out.
     */
    private function asAscii(string $html): string
    {
        return mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
    }

    private function looksLikeChallenge(string $html): bool
    {
        return str_contains($html, config('cupida.scrape.challenge_cookie') . '=')
            && str_contains($html, 'location.reload()');
    }

    private function challengeFrom(string $html): string
    {
        $cookie = preg_quote((string)config('cupida.scrape.challenge_cookie'), '/');

        if (preg_match('/' . $cookie . '=([a-f0-9]+)/i', $html, $matches) !== 1) {
            throw new RuntimeException('The shop served a challenge page this scraper could not read.');
        }

        return $matches[1];
    }

    private function request(): PendingRequest
    {
        $request = Http::withUserAgent((string)config('cupida.scrape.user_agent'))
            ->withHeaders(['Accept-Language' => 'es-ES,es;q=0.9'])
            ->timeout((int)config('cupida.scrape.timeout'))
            ->retry(2, 500, throw: false);

        return $this->challenge === null
            ? $request
            : $request->withCookies(
                [(string)config('cupida.scrape.challenge_cookie') => $this->challenge],
                (string)parse_url($this->url('/'), PHP_URL_HOST),
            );
    }
}
