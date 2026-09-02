<x-layouts.shelf
    :title="$author->name"
    :description="$author->bioExcerpt() ?? __('books.public.author.intro', ['name' => $author->name])"
    :palette="$palette"
>

<section class="bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[clamp(48px,7vw,104px)] pb-[clamp(52px,6vw,96px)] text-[var(--on-cover)]">
    <p class="m-0 mb-[18px] text-[14px] font-bold uppercase tracking-[0.26em]">{{ __('books.public.author.kicker') }}</p>

    <h1 class="m-0 max-w-[16ch] text-balance font-display text-[clamp(48px,6.4vw,104px)]/[0.94] font-normal tracking-[-0.01em]">
        {{ $author->name }}
    </h1>

    @if ($author->bio)
        <div class="rich-text mt-9 max-w-[620px] border-t border-[var(--rule)] pt-8 text-[clamp(20px,2.1vw,26px)]/[1.5] italic">
            {!! $author->bio !!}
        </div>

        <p class="mt-5 mb-0 max-w-[620px] text-[17px]/[1.5]">
            {{ __('books.public.author.intro', ['name' => $author->name]) }}
        </p>
    @else
        <p class="mt-9 mb-0 max-w-[620px] border-t border-[var(--rule)] pt-8 text-[clamp(20px,2.1vw,26px)]/[1.5] italic">
            {{ __('books.public.author.intro', ['name' => $author->name]) }}
        </p>
    @endif

    <a href="{{ route('home') }}" class="mt-8 inline-block border-b-2 border-current pb-[3px] text-[15px] font-semibold uppercase tracking-[0.12em] transition-opacity duration-150 hover:opacity-65">
        {{ __('books.public.shelf_back') }}
    </a>
</section>

<main class="bg-paper px-[clamp(22px,5vw,80px)] pt-[clamp(44px,5vw,76px)] pb-[clamp(56px,6vw,96px)] text-ink">
    <x-book-grid :books="$books" />
</main>

</x-layouts.shelf>
