{{-- The intro line is the page's meta description only; the shelf itself is
     the whole argument and does not need it written out above. --}}
<x-layouts.shelf
    :title="__('books.public.shelf.title')"
    :description="__('books.public.shelf.intro')"
    :palette="$palette"
>

<section class="bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[clamp(48px,7vw,104px)] pb-[clamp(40px,5vw,72px)] text-[var(--on-cover)]">
    <h1 class="m-0 max-w-[18ch] text-balance font-display text-[clamp(40px,5.4vw,88px)]/[0.96] font-normal tracking-[-0.01em]">
        {{ __('books.public.shelf.heading') }}
    </h1>
</section>

{{-- No side padding: a shelf runs wall to wall, so the board reaches both
     edges of the window and the row is scrolled rather than inset. --}}
<main class="bg-paper pt-[clamp(28px,3vw,48px)] pb-[clamp(48px,6vw,88px)] text-ink">
    @if ($shelved->isEmpty())
        <p class="mx-auto my-[clamp(40px,6vw,88px)] max-w-[520px] px-[clamp(22px,5vw,80px)] text-center text-balance text-[22px] italic">
            {{ __('books.public.home.empty') }}
        </p>
    @else
        <div data-shelf>
        <div class="shelf">
            <div class="shelf__scroll" data-shelf-scroll>
                <div class="shelf__floor" data-shelf-floor>
                {{-- Rendered here rather than by the script: every book is a
                     real link inside a real list, so the shelf is a shelf with
                     the physics loop switched off, and with no JavaScript at
                     all. The script only writes positions onto it. --}}
                <ul class="shelf__stage" data-shelf-stage>
                    @foreach ($shelved as $shelfBook)
                        @php($book = $shelfBook->book)
                        @php($cover = $book->coverUrl('thumb'))

                        <li>
                            <a
                                class="shelf__book"
                                href="{{ route('books.show', $book) }}"
                                draggable="false"
                                data-face="{{ $shelfBook->facesOut ? '1' : '0' }}"
                                data-measured="{{ $shelfBook->isMeasured ? '1' : '0' }}"
                                data-title="{{ $book->title }}"
                                data-author="{{ $book->authors_line }}"
                                data-note="{{ $book->priceInEuros() === null
                                    ? __('books.public.home.' . ($book->stock > 0 ? 'in_stock' : 'out_of_stock'))
                                    : number_format($book->priceInEuros(), 2, ',', '.') . ' € · ' . __('books.public.home.' . ($book->stock > 0 ? 'in_stock' : 'out_of_stock')) }}"
                                style="--mm-w: {{ $shelfBook->widthMm }}; --mm-h: {{ $shelfBook->heightMm }}; --mm-d: {{ $shelfBook->thicknessMm }}; --spine: {{ $shelfBook->palette->background }}; --spine-ink: {{ $shelfBook->palette->foreground }}"
                            >
                                <span class="shelf__box">
                                    <span class="shelf__face shelf__face--front">
                                        @if ($cover)
                                            <img
                                                src="{{ $cover }}"
                                                alt="{{ __('books.fields.cover') }}: {{ $book->title }}"
                                                draggable="false"
                                            />
                                        @else
                                            <span class="shelf__untitled">{{ $book->title }}</span>
                                        @endif
                                    </span>

                                    <span class="shelf__face shelf__face--spine" aria-hidden="true">
                                        <span class="shelf__spine-mark"></span>
                                        <span class="shelf__spine-text">{{ $shelfBook->spineLine() }}</span>
                                        <span class="shelf__spine-mark"></span>
                                    </span>

                                    <span class="shelf__face shelf__face--edge" aria-hidden="true"></span>
                                    <span class="shelf__face shelf__face--top" aria-hidden="true"></span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                    <div class="shelf__board"></div>
                </div>

                <div class="shelf__peek" data-shelf-peek aria-hidden="true">
                    <span class="shelf__peek-title" data-shelf-peek-title></span>
                    <span class="shelf__peek-author" data-shelf-peek-author></span>
                    <span class="shelf__peek-note" data-shelf-peek-note></span>
                </div>
            </div>
        </div>

    @endif
</main>

</x-layouts.shelf>
