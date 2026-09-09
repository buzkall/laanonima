@props(['title', 'description', 'palette', 'footerCta' => true, 'ogImage' => null])

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

    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:description" content="{{ str($description)->limit(155) }}" />
    <meta property="og:url" content="{{ url()->current() }}" />

    {{-- A shelf is a list of covers and no one of them speaks for the page, so
         most of these pages share without a picture. `:og-image` is for the
         page that does have a face of its own -- La Cupida -- and it takes an
         absolute URL, which is what `Vite::asset()` already returns. The card
         only grows to the wide format once there is something to put in it. --}}
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}" />
        <meta name="twitter:card" content="summary_large_image" />
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
<body
    class="bg-paper text-ink selection:text-paper flex min-h-dvh flex-col font-serif text-[20px]/[1.65] antialiased selection:bg-[var(--accent)]"
    style="--cover: {{ $palette->background }}; --on-cover: {{ $palette->foreground }}; --accent: {{ $palette->accent }}; --rule: {{ $palette->foregroundFaded() }}"
>
    <x-site-header />

    <div class="flex flex-1 flex-col">{{ $slot }}</div>

    <x-site-footer :cta="$footerCta" />
</body>
</html>
