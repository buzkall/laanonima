@props(['title', 'description', 'palette', 'footerCta' => true])

{{-- A shelf of books: the home page, an author's page, an imprint's page.
     None of them belongs to a single book, so all three wear the house
     colours rather than a cover's. The book page keeps its own shell: it is
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

    <link rel="icon" href="/favicon.ico" sizes="any" />
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />

    @fonts(['gloock', 'crimson-pro'])

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="bg-paper font-serif text-[20px]/[1.65] text-ink antialiased selection:bg-[var(--accent)] selection:text-paper"
    style="--cover: {{ $palette->background }}; --on-cover: {{ $palette->foreground }}; --accent: {{ $palette->accent }}; --rule: {{ $palette->foregroundFaded() }}"
>

<x-site-header />

{{ $slot }}

<x-site-footer :cta="$footerCta" />
</body>
</html>
