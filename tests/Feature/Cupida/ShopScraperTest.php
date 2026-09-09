<?php

use App\Support\Shop\ShopScraper;
use Illuminate\Support\Facades\Http;

/**
 * One `div.libro` card, cut down to what `cardToBook()` reads off it.
 *
 * The scraper is otherwise run by hand and untested on purpose -- it talks to
 * somebody else's site. The price is the exception: it is arithmetic on a
 * string, it lands in a committed JSON file, and getting it wrong throws
 * nothing at all.
 */
function shopListingPage(string $price): string
{
    return <<<HTML
        <html><body>
            <div class="libro">
                <a href="/libros/9788412976137/la-piedra-y-el-agua/">
                    <span class="titulo">La piedra y el agua</span>
                </a>
                <span class="autor">Sarah A. Parker</span>
                <span class="btn-compra">Comprar {$price}</span>
                <span class="existencias">Disponible</span>
            </div>
        </body></html>
        HTML;
}

function scrapedPriceCents(string $price): ?int
{
    Http::fake(['*' => Http::response(shopListingPage($price))]);

    return app(ShopScraper::class)->listing('libros/')['books'][0]['price_cents'];
}

it('reads an ordinary price', function(): void {
    expect(scrapedPriceCents('24,00 EUR'))->toBe(2400);
});

it('reads a price over a thousand as the whole number', function(): void {
    /* The shop prints Spanish numbers, so anything over a thousand carries a
       grouping dot as well as the decimal comma. Anchored on the first
       separator, "1.234,56" read as one euro twenty-three. */
    expect(scrapedPriceCents('1.234,56 EUR'))->toBe(123456);
});

it('takes the last separator as the decimal one whichever it is', function(): void {
    expect(scrapedPriceCents('12.50 EUR'))->toBe(1250);
});

it('has no price rather than a wrong one', function(): void {
    expect(scrapedPriceCents('Consultar'))->toBeNull();
});
