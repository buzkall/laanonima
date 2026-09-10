# Why a TTF lives here

The site's webfonts come from Bunny through `laravel-vite-plugin/fonts` (see
`vite.config.js`) and are served as `.woff2`. Nothing on a page needs anything
else.

The share cards do. They are drawn with GD, whose `imagettftext()` goes through
FreeType, and FreeType cannot read a woff2 — `imagettfbbox()` simply answers
`false` for one, including for the Inter that Filament publishes into
`public/fonts`. So the one face the cards set type in is committed here as a
static TrueType file.

`Gloock-Regular.ttf` is the display face of the public pages, taken from
`google/fonts` (`ofl/gloock/`). It is SIL Open Font License 1.1, which requires
the licence to travel with the font — hence `OFL.txt` beside it. Gloock is a
single weight and a single style, so this one file is the whole family.

Crimson Pro, the site's reading face, is deliberately **not** here: upstream
ships it only as a variable font, and FreeType renders a variable font at its
default instance, which for Crimson Pro is ExtraLight. It would come out a
hairline. The cards set their subtitle in small tracked Gloock instead, which is
what every kicker on the site already does.
