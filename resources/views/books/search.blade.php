{{-- A results page is one reader's query, not something the shop publishes,
     so it is kept out of the index like a shared recommendation. --}}
<x-layouts.shelf
    :title="$query === '' ? __('books.public.search.heading') : __('books.public.search.results_for', ['query' => $query])"
    :description="__('books.public.search.prompt')"
    :palette="$palette"
    :indexable="false"
>
    <section class="bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[clamp(48px,7vw,104px)] pb-[clamp(52px,6vw,96px)] text-[var(--on-cover)]">
        <h1 class="font-display m-0 max-w-[16ch] text-[clamp(40px,5.4vw,88px)]/[0.94] font-normal tracking-[-0.01em] text-balance">
            {{ $query === '' ? __('books.public.search.heading') : __('books.public.search.results_for', ['query' => $query]) }}
        </h1>

        <form
            method="GET"
            action="{{ route('books.search') }}"
            role="search"
            class="mt-9 flex max-w-[620px] items-stretch gap-3 border-t border-[var(--rule)] pt-8"
        >
            <label for="search-page-q" class="sr-only">{{ __('books.public.search.label') }}</label>
            <input
                id="search-page-q"
                name="q"
                type="search"
                maxlength="100"
                enterkeyhint="search"
                value="{{ $query }}"
                placeholder="{{ __('books.public.search.placeholder') }}"
                @if ($query === '') autofocus @endif
                class="text-ink min-w-0 flex-1 border border-[var(--rule)] bg-paper px-4 py-3 text-[20px] placeholder:text-ink/50 focus:outline-2 focus:outline-offset-0 focus:outline-[var(--accent)]"
            />
            <button
                type="submit"
                class="text-paper bg-ink px-5 py-3 text-[15px] font-semibold tracking-[0.12em] uppercase transition-opacity duration-150 hover:opacity-85"
            >{{ __('books.public.search.submit') }}</button>
        </form>
    </section>

    <main class="bg-paper text-ink px-[clamp(22px,5vw,80px)] pt-[clamp(44px,5vw,76px)] pb-[clamp(56px,6vw,96px)]">
        @if ($books === null)
            <p class="mx-auto my-[clamp(40px,6vw,88px)] max-w-[520px] text-center text-[22px] text-balance italic">
                {{ __('books.public.search.prompt') }}
            </p>
        @elseif ($books->isEmpty())
            <p class="mx-auto my-[clamp(40px,6vw,88px)] max-w-[520px] text-center text-[22px] text-balance italic">
                {{ __('books.public.search.empty', ['query' => $query]) }}
            </p>
        @else
            @if ($resembling)
                <p class="m-0 mb-[clamp(36px,4vw,56px)] max-w-[620px] text-[20px] text-balance italic">
                    {{ __('books.public.search.resembling', ['query' => $query]) }}
                </p>
            @endif

            <x-book-grid :books="$books" />
        @endif
    </main>
</x-layouts.shelf>
