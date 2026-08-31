<x-layouts.shelf
    :title="__('books.public.tagline')"
    :description="__('books.public.home.intro')"
    :palette="$palette"
>

<section class="bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[clamp(48px,7vw,104px)] pb-[clamp(52px,6vw,96px)] text-[var(--on-cover)]">
    <h1 class="m-0 max-w-[16ch] text-balance font-display text-[clamp(48px,6.4vw,104px)]/[0.94] font-normal tracking-[-0.01em]">
        {{ __('books.public.home.heading') }}
    </h1>

    <p class="mt-9 mb-0 max-w-[620px] border-t border-[var(--rule)] pt-8 text-[clamp(20px,2.1vw,26px)]/[1.5] italic">
        {{ __('books.public.home.intro') }}
    </p>
</section>

<main class="bg-paper px-[clamp(22px,5vw,80px)] pt-[clamp(44px,5vw,76px)] pb-[clamp(56px,6vw,96px)] text-ink">
    @if ($books->isEmpty())
        <p class="mx-auto my-[clamp(40px,6vw,88px)] max-w-[520px] text-center text-balance text-[22px] italic">
            {{ __('books.public.home.empty') }}
        </p>
    @else
        <x-book-grid :books="$books" />
    @endif
</main>

</x-layouts.shelf>
