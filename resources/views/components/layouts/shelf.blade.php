@props(['title', 'description', 'palette', 'footerCta' => true, 'footerOnPhone' => true, 'fitsViewport' => false, 'ogImage' => null, 'ogImageAlt' => null, 'indexable' => true])

{{-- A shelf of books: the home page, an author's page, an imprint's page.
     None of them belongs to a single book, so all three wear the house
     colors rather than a cover's. The book page keeps its own shell: it is
     painted per record and carries the floating cover. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{{ $title }} — {{ config('app.name') }}</title>
    <meta name="description" content="{{ str($description)->limit(155) }}" />
    <meta name="theme-color" content="{{ $palette->background }}" />

    {{-- `:indexable="false"` is a shared recommendation, and only that. One
         reader's session is not a page the shop is publishing: there is one per
         game played, each is a paragraph about a book that already has a page
         of its own, and the only person who should ever reach one is somebody
         who was handed the link. `noindex` and not `nofollow` -- the links out
         of it go to our own book pages and are worth following. --}}
    @unless ($indexable)
        <meta name="robots" content="noindex" />
    @endunless

    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:description" content="{{ str($description)->limit(155) }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:site_name" content="{{ config('app.name') }}" />
    <meta property="og:locale" content="es_ES" />

    {{-- `:og-image` takes an absolute URL. La Cupida passes a committed JPEG,
         which is what `Vite::asset()` returns; an author's page and an
         imprint's pass a card drawn for them. The home page and the estantería
         still pass nothing -- neither is about one thing, so there is no
         picture that speaks for them -- and get the small card. --}}
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}" />
        <meta property="og:image:secure_url" content="{{ $ogImage }}" />
        <meta property="og:image:type" content="image/jpeg" />
        <meta property="og:image:width" content="{{ config('og.width') }}" />
        <meta property="og:image:height" content="{{ config('og.height') }}" />
        @if ($ogImageAlt)
            <meta property="og:image:alt" content="{{ $ogImageAlt }}" />
        @endif
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:image" content="{{ $ogImage }}" />
    @else
        <meta name="twitter:card" content="summary" />
    @endif

    <link rel="icon" href="/favicon.ico" sizes="any" />
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />

    @fonts(['gloock', 'crimson-pro'])

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- A column as tall as the window, so a page with little on it -- an author
     with two books, La Cupida with one -- does not leave the footer floating in
     the middle of the screen with cream below it. The page between the header
     and the footer takes whatever is left over; what it does with the room is
     its own business. --}}
{{-- `fits-viewport` swaps that minimum for a definite `h-dvh`, and that one
     word is what lets a page fit a phone without a measurement written into it.
     A minimum bounds nothing: flexbox can only take space away from a child when
     the parent has a ceiling, so under `min-h-dvh` a panel taller than the
     window just grows and the page scrolls, whatever its children were told
     they may give up. Put a height on the shell and the same flex rules that
     already share out the slack start reclaiming it as well.

     Opt-in, because it is only right for a page that is a screen rather than a
     document -- the shelf and the author pages are lists that must be free to
     run past the fold. A page that opts in owns the consequence: anything in it
     that can outgrow the window has to say how it scrolls, or it is cut off.

     And only below `wide:`. A phone holds one thing at a time and a page that
     does not fit it is a page with its controls under the fold; a laptop has
     room for a heading set at 5.6vw AND everything under it, and squeezing a
     document into a window that was never the constraint costs the design
     without buying anything. So the height is the narrow layout's, and from
     `wide:` up the shell goes back to a minimum. --}}
<body
    @class([
    'bg-paper text-ink selection:text-paper flex flex-col font-serif text-[20px]/[1.65] antialiased selection:bg-[var(--accent)]',
    'min-h-dvh' => ! $fitsViewport,
    'h-dvh wide:h-auto wide:min-h-dvh' => $fitsViewport,
                ])
    style="--cover: {{ $palette->background }}; --on-cover: {{ $palette->foreground }}; --accent: {{ $palette->accent }}; --rule: {{ $palette->foregroundFaded() }}; --wordmark-blend: {{ $palette->wordmarkBlend() }}"
>
    <x-site-header />

    {{-- `min-h-0` is the other half of it. A flex item refuses to shrink below
         its own content unless it is told it may, and one link in the chain that
         was not told breaks it for everything underneath. --}}
    <div @class(['flex flex-1 flex-col', 'min-h-0' => $fitsViewport])>{{ $slot }}</div>

    <x-site-footer :cta="$footerCta" :on-phone="$footerOnPhone" />
</body>
</html>
