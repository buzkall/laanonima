---
paths:
  - 'config/cupida.php,app/Support/Cupida/CupidaDeck.php,app/Support/Cupida/CupidaCard.php'
---

# Cupida Support Cupida

## A mood is defined in three places
Adding a mood to the third round means three entries under the same key: keywords in `cupida.moods`, an outline Heroicon name in `cupida.mood_icons` (without the `heroicon-o-` prefix), and a label in `lang/{es,en}/cupida.php`. `CupidaDeckTest` fails when the two config lists diverge or an icon name resolves to no SVG, and `TranslationsTest` when the label is missing. Only a mood card carries `CupidaCard::$icon`; the view draws it in the slot an author portrait takes, with `pointer-events-none` so the SVG can never swallow the drag.
