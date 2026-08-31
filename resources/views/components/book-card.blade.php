@props(['book'])

@php
    /* Every cover sits on the colour read off it, so a portrait, a square and
       a missing image all fill the same box. */
    $palette = App\Support\CoverPalette::fromCover($book->cover_color);
    $cover = $book->coverUrl('thumb');
@endphp

<li>
    <a
        href="{{ route('books.show', $book) }}"
        class="group flex h-full flex-col no-underline"
        style="--card: {{ $palette->background }}; --on-card: {{ $palette->foreground }}"
    >
        <div class="relative flex aspect-[2/3] items-center justify-center overflow-hidden bg-[var(--card)]">
            @if ($cover)
                <img
                    src="{{ $cover }}"
                    alt="{{ __('books.fields.cover') }}: {{ $book->title }}"
                    loading="lazy"
                    class="max-h-full w-auto max-w-full object-contain transition-transform duration-300 ease-out group-hover:scale-[1.04]"
                />
            @else
                <span class="flex h-full w-full items-end p-4 font-display text-[20px]/[1.1] text-[var(--on-card)]">
                    {{ $book->title }}
                </span>
            @endif

            @if ($book->is_featured)
                <span class="absolute top-0 left-0 bg-[var(--accent)] px-[10px] py-[5px] text-[11px] font-bold uppercase tracking-[0.18em] text-paper">
                    {{ __('books.public.home.featured') }}
                </span>
            @endif
        </div>

        <h2 class="mt-[18px] mb-0 text-balance font-display text-[22px]/[1.15] font-normal transition-colors duration-150 group-hover:text-[var(--accent)]">
            {{ $book->title }}
        </h2>

        @if ($book->authors_line)
            <p class="mt-[6px] mb-0 text-[17px]/[1.35] italic opacity-80">{{ $book->authors_line }}</p>
        @endif

        <div class="mt-auto flex items-baseline justify-between gap-3 border-t border-dotted border-ink pt-3">
            @if ($book->priceInEuros() !== null)
                <span class="font-display text-[19px]">{{ number_format($book->priceInEuros(), 2, ',', '.') }}&nbsp;€</span>
            @else
                <span></span>
            @endif

            <span class="text-[13px] font-semibold uppercase tracking-[0.14em] opacity-70">
                {{ $book->stock > 0 ? __('books.public.home.in_stock') : __('books.public.home.out_of_stock') }}
            </span>
        </div>
    </a>
</li>
