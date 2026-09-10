---
paths:
  - 'app/Filament/Resources/**/Tables/*.php'
---

# Tables

## Filters above a listing are collapsible, reopened at lg by the theme
Every admin table that shows its filters above the content passes `layout: FiltersLayout::AboveContentCollapsible`, never plain `AboveContent`: only the collapsible layout draws the "Filtrar" trigger, and without it a phone or a portrait iPad loses half the screen to the filter row before the first row of the table.

Collapsed is Filament's own starting state (`areFiltersOpen: false`). Desktop is put back by CSS in `resources/css/filament/admin/theme.css`, which forces `.fi-ta-filters-above-content-ctn > .fi-ta-filters` to `lg:grid!` and hides the trigger at `lg` — the `!` is load-bearing, because `x-show` writes `display: none` inline. `lg` (64rem) is Filament's own breakpoint for collapsing the before/after-content layouts and folding the sidebar.

`tests/Feature/Filament/CollapsibleFiltersTest.php` asserts the layout on all five listings, so a revert to `AboveContent` fails there rather than silently on a phone. Changing the CSS means `npm run build`.
