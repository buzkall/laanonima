@use('App\Models\Book')
@php
    $cover = $book->coverUrl();
    $inStock = $book->stock > 0;
    $stock = $inStock ? 'in_stock' : 'out_of_stock';

    /* Only the in-stock "keep it for me" is still an email; ordering goes
       through the form below. */
    $mailto = 'mailto:' . config('site.contact_email')
        . '?subject=' . rawurlencode(__('books.public.in_stock.subject', ['title' => $book->title]));

    /* A book we have is still put aside by writing to us; one we have run out
       of goes through the request form, which reaches the panel with this book
       already attached. */
    $orderUrl = route('book-requests.create.book', $book);

    $cta = $inStock
        ? ['label' => __('books.public.buy'), 'href' => '#comprar', 'note' => __('books.public.in_stock_note')]
        : ['label' => __('books.public.out_of_stock.cta'), 'href' => $orderUrl, 'note' => __('books.public.out_of_stock_note')];

    $publishedOn = $book->published_on
        ?->locale(app()->getLocale())
        ->isoFormat(__('books.public.published_format'));

    /* Only what this particular book actually knows about itself. */
    $facts = array_filter([
        __('books.fields.binding') => $book->binding?->getLabel(),
        __('books.fields.pages') => $book->pages,
        __('books.fields.measures') => $book->height_mm && $book->width_mm
            ? __('books.public.measures', [
                'height' => round($book->height_mm / 10),
                'width' => round($book->width_mm / 10),
            ])
            : null,
        __('books.fields.language') => $book->language->getLabel(),
        __('books.fields.publisher_id') => $book->publisher?->name,
        __('books.fields.collection_name') => $book->collection_name,
        __('books.fields.published_on') => $publishedOn === null ? null : str($publishedOn)->ucfirst(),
        __('books.fields.isbn13') => $book->isbn13,
    ]);

    /* The cover leads on its own; the rest of the collection illustrates the object. */
    $gallery = $book->gallery();

    /* Names that have a shelf of their own become links; the rest are text. */
    $authors = $book->authors();

    $contributors = collect($book->contributors ?? [])
        ->filter(fn (array $person): bool => filled($person['name'] ?? null))
        ->map(fn (array $person): array => [
            ...$person,
            'href' => ($person['role'] ?? null) === 'author'
                ? route('authors.show', Book::authorSlug($person['name']))
                : null,
        ]);

    $factLinks = array_filter([
        __('books.fields.publisher_id') => $book->publisher === null ? null : route('publishers.show', $book->publisher),
    ]);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{{ $book->title }}{{ $book->authors_line ? ', de ' . $book->authors_line : '' }} — {{ config('app.name') }}</title>
    <meta name="description" content="{{ str(strip_tags($book->synopsis ?? $book->subtitle ?? $book->title))->limit(155) }}" />
    <meta name="theme-color" content="{{ $palette->background }}" />

    <meta property="og:type" content="book" />
    <meta property="og:title" content="{{ $book->title }}" />
    <meta property="og:description" content="{{ str(strip_tags($book->synopsis ?? $book->subtitle ?? $book->title))->limit(155) }}" />
    <meta property="og:url" content="{{ route('books.show', $book) }}" />
    @if ($cover)
        <meta property="og:image" content="{{ $cover }}" />
    @endif

    <link rel="icon" href="/favicon.ico" sizes="any" />
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />

    @fonts(['gloock', 'crimson-pro'])

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="bg-paper font-serif text-[20px]/[1.65] text-ink antialiased selection:bg-[var(--accent)] selection:text-paper"
    style="--top-bar: 57px; --cover: {{ $palette->background }}; --on-cover: {{ $palette->foreground }}; --accent: {{ $palette->accent }}; --rule: {{ $palette->foregroundFaded() }}"
>

<x-site-header />

<div class="grid grid-cols-1 wide:grid-cols-[0.92fr_1.08fr] wide:items-stretch">

    {{-- Below 1000px the cover simply sits on top; above it, this column is the
         hole left for a cover that floats fixed over the whole page. --}}
    <div class="relative bg-[var(--cover)] px-[22px] pt-10 pb-12 wide:h-full wide:self-stretch wide:bg-transparent wide:p-0">
        <div data-band class="absolute inset-x-0 top-0 hidden bg-[var(--cover)] wide:block"></div>

        <div
            data-cover-sticky
            class="static wide:pointer-events-none wide:fixed wide:top-[var(--top-bar)] wide:bottom-0 wide:left-0 wide:z-30 wide:box-border wide:flex wide:w-[46%] wide:items-start wide:justify-start wide:pt-[clamp(24px,3.5vw,56px)] wide:pb-[clamp(16px,2vw,36px)] wide:pl-[clamp(16px,2vw,36px)]"
        >
            @if ($cover)
                <img
                    src="{{ $cover }}"
                    alt="{{ __('books.fields.cover') }}: {{ $book->title }}"
                    data-cover
                    class="mx-auto block h-auto w-full max-w-[420px] shadow-[0_2px_8px_rgba(33,21,17,0.18),0_26px_60px_rgba(33,21,17,0.34)] wide:m-0 wide:max-h-full wide:w-auto wide:max-w-full wide:origin-top-left wide:will-change-transform"
                />
            @else
                <div data-cover class="mx-auto flex aspect-[2/3] w-full max-w-[420px] items-end border border-[var(--rule)] p-6 text-[var(--on-cover)] shadow-[0_2px_8px_rgba(33,21,17,0.18),0_26px_60px_rgba(33,21,17,0.34)] wide:m-0 wide:h-full wide:max-h-[600px] wide:w-auto wide:max-w-full wide:min-w-[280px]">
                    <span class="font-display text-[32px]/[1.05]">{{ $book->title }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="min-w-0">

        <section
            data-hero
            class="bg-[var(--cover)] px-[clamp(22px,4vw,56px)] pt-[clamp(40px,7vw,96px)] pr-[clamp(22px,4vw,64px)] pb-[clamp(48px,6vw,88px)] text-[var(--on-cover)]"
        >
            <h1 class="m-0 text-balance font-display text-[clamp(52px,6.4vw,104px)]/[0.92] font-normal tracking-[-0.01em]">{{ $book->title }}</h1>

            @if ($authors !== [])
                <p class="mt-6 mb-0 text-[clamp(22px,2.4vw,30px)]">
                    @foreach ($authors as $author)
                        <a
                            href="{{ route('authors.show', $author['slug']) }}"
                            class="underline decoration-[var(--rule)] decoration-1 underline-offset-[7px] transition-opacity duration-150 hover:opacity-65"
                        >{{ $author['name'] }}</a>@unless ($loop->last),@endunless
                    @endforeach
                </p>
            @elseif ($book->authors_line)
                <p class="mt-6 mb-0 text-[clamp(22px,2.4vw,30px)]">{{ $book->authors_line }}</p>
            @endif

            <div class="mt-11 flex flex-wrap items-center gap-x-7 gap-y-4">
                @if ($book->priceInEuros() !== null)
                    <span class="font-display text-[30px]">{{ number_format($book->priceInEuros(), 2, ',', '.') }}&nbsp;€</span>
                @endif

                <a
                    href="{{ $cta['href'] }}"
                    data-buy
                    class="bg-[var(--on-cover)] px-6 py-3 text-[16px] font-semibold uppercase tracking-[0.06em] text-[var(--cover)] transition-opacity duration-150 hover:opacity-85"
                >
                    {{ $cta['label'] }}
                </a>

                <span class="text-[15px] italic opacity-85">{{ $cta['note'] }}</span>
            </div>

            @if ($book->subtitle)
                <p class="mt-[clamp(56px,6vw,84px)] mb-0 max-w-[620px] border-t border-[var(--rule)] pt-10 text-balance text-[clamp(26px,2.7vw,36px)]/[1.25] italic">
                    {{ $book->subtitle }}
                </p>
            @endif
        </section>

        <div class="bg-paper px-[clamp(22px,4vw,56px)] pt-[clamp(48px,5vw,76px)] pr-[clamp(22px,4vw,64px)] pb-[clamp(56px,6vw,84px)] text-ink">

            @if ($book->synopsis)
                <section class="max-w-[560px]">
                    <h2 class="m-0 mb-[22px] text-[14px] font-bold uppercase tracking-[0.26em] text-[var(--accent)]">{{ __('books.public.synopsis_kicker') }}</h2>
                    <div class="flex flex-col gap-[22px]">
                        @foreach (preg_split('/\R{1,}/', trim($book->synopsis), -1, PREG_SPLIT_NO_EMPTY) as $paragraph)
                            <p class="m-0">{{ $paragraph }}</p>
                        @endforeach

                        @if ($book->back_cover_text)
                            <p class="m-0 border-l-2 border-[var(--accent)] pl-5 italic">{{ $book->back_cover_text }}</p>
                        @endif
                    </div>
                </section>
            @endif

            @if ($facts !== [])
                <section @class([
                    'max-w-[560px]',
                    'mt-[clamp(56px,6vw,88px)] border-t border-ink pt-11' => filled($book->synopsis),
                ])>
                    <h2 class="m-0 mb-[30px] text-[14px] font-bold uppercase tracking-[0.26em] text-[var(--accent)]">{{ __('books.public.object_kicker') }}</h2>

                    @if ($gallery->isNotEmpty())
                        <div class="mb-[34px] grid grid-cols-[repeat(auto-fill,minmax(min(100%,150px),1fr))] gap-3">
                            @foreach ($gallery as $image)
                                <img
                                    src="{{ $image->getAvailableUrl(['thumb']) }}"
                                    alt="{{ __('books.fields.covers') }}: {{ $book->title }}"
                                    loading="lazy"
                                    class="block h-auto w-full border border-ink"
                                />
                            @endforeach
                        </div>
                    @endif

                    <dl class="m-0 flex flex-col">
                        @foreach ($facts as $label => $value)
                            <div class="flex items-baseline justify-between gap-4 border-b border-dotted border-ink py-[13px]">
                                <dt class="text-[14px] font-semibold uppercase tracking-[0.14em]">{{ $label }}</dt>
                                <dd class="m-0 text-right text-[19px] italic">
                                    @isset ($factLinks[$label])
                                        <a href="{{ $factLinks[$label] }}" class="text-[var(--accent)] transition-opacity duration-150 hover:opacity-65">{{ $value }}</a>
                                    @else
                                        {{ $value }}
                                    @endisset
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>
    </div>
</div>

@if ($contributors->isNotEmpty())
    <section class="border-t border-ink bg-paper px-[clamp(22px,5vw,80px)] pt-[clamp(52px,5vw,88px)] pb-[clamp(60px,6vw,96px)] text-ink wide:pl-[46vw]">
        <h2 class="m-0 mb-[34px] text-[14px] font-bold uppercase tracking-[0.26em] text-[var(--accent)]">{{ __('books.public.authors_kicker') }}</h2>

        <div class="grid max-w-[860px] grid-cols-[repeat(auto-fit,minmax(240px,1fr))] items-start gap-x-12 gap-y-10">
            <ul class="m-0 flex list-none flex-col gap-3 p-0">
                @foreach ($contributors as $person)
                    <li class="flex items-baseline justify-between gap-4 border-b border-dotted border-ink pb-[10px]">
                        @if ($person['href'])
                            <a href="{{ $person['href'] }}" class="font-display text-[26px] text-[var(--accent)] transition-opacity duration-150 hover:opacity-65">{{ $person['name'] }}</a>
                        @else
                            <span class="font-display text-[26px]">{{ $person['name'] }}</span>
                        @endif
                        @if (filled($person['role'] ?? null))
                            <span class="whitespace-nowrap text-[16px] italic">{{ __('books.contributor_role.' . $person['role']) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($alsoByAuthors->isNotEmpty())
                <div class="border-t border-ink pt-[18px]">
                    <h3 class="m-0 mb-3 text-[13px] font-bold uppercase tracking-[0.2em]">{{ __('books.public.also_by_them') }}</h3>
                    <ul class="m-0 flex list-none flex-col gap-[10px] p-0">
                        @foreach ($alsoByAuthors as $other)
                            <li class="flex items-baseline justify-between gap-4 border-b border-dotted border-ink pb-[10px]">
                                <a href="{{ route('books.show', $other) }}" class="font-display text-[22px] text-[var(--accent)] transition-opacity duration-150 hover:opacity-65">{{ $other->title }}</a>
                                @if ($other->published_year)
                                    <span class="whitespace-nowrap text-[16px] italic">{{ $other->published_year }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-[14px] mb-0 text-[16px] italic opacity-80">{{ __('books.public.also_note') }}</p>
                </div>
            @endif
        </div>
    </section>
@endif

@if ($book->publisher && ($book->publisher->description || $fromPublisher->isNotEmpty()))
    <section class="bg-[var(--cover)] px-[max(clamp(22px,5vw,80px),calc(50vw-320px))] pt-[clamp(52px,5vw,88px)] pb-[clamp(60px,6vw,96px)] text-[var(--on-cover)] wide:pr-[clamp(22px,5vw,80px)] wide:pl-[46vw]">
        <div class="mx-auto max-w-[640px] text-center">
            <h2 class="m-0 mb-[18px] text-[14px] font-bold uppercase tracking-[0.26em]">
                {{ __('books.public.publisher_kicker', ['publisher' => $book->publisher->name]) }}
            </h2>

            @if ($book->publisher->logoUrl('thumb'))
                <img src="{{ $book->publisher->logoUrl('thumb') }}" alt="{{ $book->publisher->name }}" class="mx-auto mb-7 block h-[72px] w-auto" />
            @endif

            @if ($book->publisher->description)
                <p class="mx-auto mb-9 max-w-[560px] text-[20px]/[1.6]">{{ $book->publisher->description }}</p>
            @endif

            @if ($fromPublisher->isNotEmpty())
                <p class="mx-auto mb-9 max-w-[560px] text-[20px]/[1.6]">{{ __('books.public.publisher_intro') }}</p>

                <ul class="m-0 flex list-none flex-col p-0">
                    @foreach ($fromPublisher as $other)
                        <li @class([
                            'flex flex-col gap-[2px] border-t border-[var(--rule)] py-4',
                            'border-b' => $loop->last,
                        ])>
                            <a href="{{ route('books.show', $other) }}" class="font-display text-[26px] transition-opacity duration-150 hover:opacity-65">{{ $other->title }}</a>
                            @if ($other->authors_line)
                                <span class="text-[17px] italic">{{ $other->authors_line }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <a
                href="{{ route('publishers.show', $book->publisher) }}"
                class="mt-9 inline-block border-b-2 border-current pb-[3px] text-[15px] font-semibold uppercase tracking-[0.12em] transition-opacity duration-150 hover:opacity-65"
            >
                {{ __('books.public.publisher.all', ['publisher' => $book->publisher->name]) }}
            </a>
        </div>
    </section>
@endif

<footer id="comprar" class="bg-paper px-[max(clamp(22px,5vw,80px),calc(50vw-320px))] pt-[clamp(64px,7vw,104px)] pb-[clamp(72px,8vw,112px)] text-center text-ink">
    <p class="mx-auto my-0 max-w-[620px] text-balance font-display text-[clamp(34px,6vw,58px)]/[1.05]">{{ __("books.public.{$stock}.heading") }}</p>
    <p class="mx-auto mt-5 mb-0 max-w-[480px] text-[20px] italic">{{ __("books.public.{$stock}.body") }}</p>

    @if ($inStock)
        <a href="{{ $mailto }}" class="mt-[34px] inline-block border-b-2 border-[var(--accent)] pb-[3px] text-[18px] font-semibold uppercase tracking-[0.08em] text-[var(--accent)] transition-opacity duration-150 hover:opacity-65">
            {{ __('books.public.in_stock.cta') }}
        </a>
    @else
        <a href="{{ $orderUrl }}" class="mt-[34px] inline-block bg-[var(--accent)] px-6 py-3 text-[18px] font-semibold uppercase tracking-[0.08em] text-paper transition-opacity duration-150 hover:opacity-85">
            {{ __('books.public.out_of_stock.cta') }}
        </a>
    @endif

    <p class="mt-16 mb-0 text-[14px] uppercase tracking-[0.2em] opacity-70">
        {{ __('books.public.footer_line', ['name' => config('app.name')]) }} ·
        <a href="mailto:{{ config('site.contact_email') }}" class="tracking-[0.2em] text-[var(--accent)] transition-opacity duration-150 hover:opacity-65">{{ config('site.contact_email') }}</a>
    </p>
</footer>

{{-- Phone only: the hero's buy button, pinned once it has scrolled out of sight. --}}
<div class="h-[84px] wide:hidden" aria-hidden="true"></div>

<div
    data-buy-bar
    class="fixed inset-x-0 bottom-0 z-40 flex translate-y-full items-center gap-4 border-t border-[var(--rule)] bg-[var(--cover)] px-[22px] py-3 text-[var(--on-cover)] transition-transform duration-200 wide:hidden"
>
    @if ($book->priceInEuros() !== null)
        <span class="font-display text-[24px]">{{ number_format($book->priceInEuros(), 2, ',', '.') }}&nbsp;€</span>
    @endif

    <a
        href="{{ $cta['href'] }}"
        class="ml-auto bg-[var(--on-cover)] px-5 py-3 text-[15px] font-semibold uppercase tracking-[0.06em] text-[var(--cover)] transition-opacity duration-150 hover:opacity-85"
    >
        {{ $cta['label'] }}
    </a>
</div>

<script>
    /*
     | Two things follow the scroll position, on one throttled handler.
     |
     | On a phone, the bar at the bottom of the window duplicates the hero's
     | buy button, so it stays tucked away until that button has scrolled past.
     | It is driven from here rather than by an IntersectionObserver because an
     | observer nothing holds a reference to can be collected mid-page.
     |
     | On a wide screen, the cover starts centred in its column, against the
     | hero colour, and slides left until it is half cut off by the edge of the
     | window, where it stays floating over the cream. The coloured band behind
     | it is only as tall as the hero, so it has to be re-measured whenever the
     | hero resizes -- including when the webfonts land and the headline
     | reflows.
     */
    (function () {
        const cover = document.querySelector('[data-cover]');
        const band = document.querySelector('[data-band]');
        const hero = document.querySelector('[data-hero]');
        const topBar = document.querySelector('header');
        const buyButton = document.querySelector('[data-buy]');
        const buyBar = document.querySelector('[data-buy-bar]');

        if (!cover || !hero) {
            return;
        }

        let frame = null;

        const paint = () => {
            if (topBar) {
                document.body.style.setProperty('--top-bar', topBar.getBoundingClientRect().height + 'px');
            }

            if (buyBar && buyButton) {
                /* Tuck the bar away only while the button it duplicates is
                   actually on screen -- including before it, since on a phone
                   the cover fills the first screenful and the button is below
                   the fold. */
                const button = buyButton.getBoundingClientRect();

                buyBar.classList.toggle('translate-y-full', button.bottom > 0 && button.top < window.innerHeight);
            }

            if (window.innerWidth < 1000) {
                cover.style.transform = '';

                return;
            }

            if (band) {
                band.style.height = hero.getBoundingClientRect().height + 'px';
            }

            const doc = document.scrollingElement || document.documentElement;
            const progress = Math.min(1, Math.max(0, doc.scrollTop / (window.innerHeight * 0.85)));
            const eased = progress * progress * (3 - 2 * progress);

            cover.style.transform = '';
            const atRest = cover.getBoundingClientRect();

            cover.style.transform = 'translateX(' + (-eased * (atRest.left + atRest.width * 0.5)).toFixed(1) + 'px)';
        };

        const schedule = () => {
            if (frame) {
                return;
            }

            frame = requestAnimationFrame(() => {
                frame = null;
                paint();
            });
        };

        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule);

        paint();

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(paint);
        }

        if (window.ResizeObserver) {
            new ResizeObserver(schedule).observe(hero);
        }
    })();
</script>
</body>
</html>
