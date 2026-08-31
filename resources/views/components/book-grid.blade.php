@props(['books'])

<p class="m-0 mb-[clamp(28px,3vw,44px)] text-[14px] font-bold uppercase tracking-[0.26em] text-[var(--accent)]">
    {{ trans_choice('books.public.home.count', $books->total(), ['count' => $books->total()]) }}
</p>

<ul class="m-0 grid list-none grid-cols-[repeat(auto-fill,minmax(min(100%,190px),1fr))] gap-x-[clamp(20px,2.5vw,40px)] gap-y-[clamp(34px,4vw,60px)] p-0">
    @foreach ($books as $book)
        <x-book-card :book="$book" />
    @endforeach
</ul>

@if ($books->hasPages())
    <nav class="mt-[clamp(48px,5vw,80px)] flex items-baseline justify-between gap-6 border-t border-ink pt-7 text-[15px] font-semibold uppercase tracking-[0.12em]">
        @if ($books->previousPageUrl())
            <a href="{{ $books->previousPageUrl() }}" rel="prev" class="border-b-2 border-[var(--accent)] pb-[3px] text-[var(--accent)] transition-opacity duration-150 hover:opacity-65">{{ __('books.public.home.prev') }}</a>
        @else
            <span></span>
        @endif

        @if ($books->nextPageUrl())
            <a href="{{ $books->nextPageUrl() }}" rel="next" class="border-b-2 border-[var(--accent)] pb-[3px] text-[var(--accent)] transition-opacity duration-150 hover:opacity-65">{{ __('books.public.home.next') }}</a>
        @else
            <span></span>
        @endif
    </nav>
@endif
