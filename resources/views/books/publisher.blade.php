<x-layouts.shelf
    :title="$publisher->name"
    :description="$publisher->description ?? __('books.public.publisher.intro', ['publisher' => $publisher->name])"
    :palette="$palette"
    :og-image="$shareCard"
    :og-image-alt="__('books.public.share.publisher_alt', ['publisher' => $publisher->name])"
>
    <section class="bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[clamp(48px,7vw,104px)] pb-[clamp(52px,6vw,96px)] text-[var(--on-cover)]">
        <p class="m-0 mb-[18px] text-[14px] font-bold tracking-[0.26em] uppercase">
            {{ __('books.public.publisher.kicker') }}
        </p>

        @if ($publisher->logoUrl('thumb'))
            <img
                src="{{ $publisher->logoUrl('thumb') }}"
                alt="{{ $publisher->name }}"
                class="mb-7 block h-[72px] w-auto"
            />
        @endif

        <h1 class="font-display m-0 max-w-[16ch] text-[clamp(48px,6.4vw,104px)]/[0.94] font-normal tracking-[-0.01em] text-balance">
            {{ $publisher->name }}
        </h1>

        <p class="mt-9 mb-0 max-w-[620px] border-t border-[var(--rule)] pt-8 text-[clamp(20px,2.1vw,26px)]/[1.5] italic">
            {{ $publisher->description ?: __('books.public.publisher.intro', ['publisher' => $publisher->name]) }}
        </p>

        <div class="mt-8 flex flex-wrap items-baseline gap-x-8 gap-y-3 text-[15px] font-semibold tracking-[0.12em] uppercase">
            <a
                href="{{ route('home') }}"
                class="border-b-2 border-current pb-[3px] transition-opacity duration-150 hover:opacity-65"
            >
                {{ __('books.public.shelf_back') }}
            </a>

            @if ($publisher->website)
                <a
                    href="{{ $publisher->website }}"
                    rel="nofollow noopener"
                    target="_blank"
                    class="border-b-2 border-current pb-[3px] transition-opacity duration-150 hover:opacity-65"
                >
                    {{ __('books.public.publisher.website') }}
                </a>
            @endif

            {{-- Same control as the book page, and the sentence it carries
                 says whose shelf this is and which shop it is in. --}}
            <x-share-button
                :url="route('publishers.show', $publisher)"
                :title="$publisher->name"
                :text="__('books.public.share.publisher_message', ['publisher' => $publisher->name, 'shop' => config('app.name')])"
                :label="__('books.public.share.action')"
                :copied="__('books.public.share.copied')"
                class="text-[15px] tracking-[0.12em] opacity-75 hover:opacity-100"
            />
        </div>
    </section>

    <main class="bg-paper text-ink px-[clamp(22px,5vw,80px)] pt-[clamp(44px,5vw,76px)] pb-[clamp(56px,6vw,96px)]">
        @if ($books->isEmpty())
            <p class="mx-auto my-[clamp(40px,6vw,88px)] max-w-[520px] text-center text-[22px] text-balance italic">
                {{ __('books.public.publisher.empty', ['publisher' => $publisher->name]) }}
            </p>
        @else
            <x-book-grid :books="$books" />
        @endif
    </main>
</x-layouts.shelf>
