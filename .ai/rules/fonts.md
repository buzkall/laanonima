---
paths:
  - 'app/Support/Og/**, app/Actions/Images/**, config/og.php, resources/fonts/**'
---

# Fonts

## Pages about one thing share a drawn card, not a bare cover
A book, an author and an imprint each get a generated 1200x630 JPEG for og:image. Never pass a raw cover: it is a 2:3 portrait and every scraper crops it to 1.91:1, which is a strip of the middle of the cover with no title on it.

`OgCardKey` is the cheap half (fingerprint + URL, columns only) and a page holds one on every render; `OgCard` is the expensive half (decodes bytes) and is built lazily, only when a card must actually be drawn. `OgCardStore::url()` files it on the `og` disk and sweeps the record's older cards.

The fingerprint is derived from the record, not stored beside it, so nothing has to listen for a change. It includes `config('og.version')` — **bump that by hand whenever the drawing changes**, or every card already on disk stays frozen at the old design.

The title is drawn in `CoverPalette->accent` on the cream half. That is load-bearing, not decorative: accent is defined as the cover color darkened until it clears 4.5:1 against the paper, so contrast is guaranteed for every book rather than checked per book. Do not set the title in any other color.

GD text needs a real TTF. `resources/fonts/Gloock-Regular.ttf` is committed for exactly that; FreeType cannot read a woff2, so neither the Bunny webfonts nor Filament's Inter can be used. See resources/fonts/README.md.
