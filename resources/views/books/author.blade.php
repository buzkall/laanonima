@php
    $portrait = $author->portrait();

    /* Commons photos come with a license that asks to be named; a photo the
       shop uploaded itself has nothing to say here. */
    $credit = $portrait?->getCustomProperty('credit');
    $credit = is_array($credit) && filled($credit['license'] ?? null) ? $credit : null;

    /* What a reader sends somebody is the person's shelf, and the sentence
       that goes with it names the shop -- the address alone is a slug. */
    $shareMessage = __('books.public.share.author_message', ['name' => $author->name, 'shop' => config('app.name')]);
@endphp
<x-layouts.shelf
    :title="$author->name"
    :description="$author->bioExcerpt() ?? __('books.public.author.intro', ['name' => $author->name])"
    :palette="$palette"
    :og-image="$shareCard"
    :og-image-alt="__('books.public.share.author_alt', ['name' => $author->name])"
>
    {{-- The portrait, where there is one, stands to the right of the name from
     `wide:` up and folds under the text on a phone, so the name is never
     pushed below the fold by a face. --}}
    <section class="wide:flex wide:items-start wide:justify-between wide:gap-[clamp(32px,5vw,96px)] bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[clamp(48px,7vw,104px)] pb-[clamp(52px,6vw,96px)] text-[var(--on-cover)]">
        <div class="min-w-0 flex-1">
            <p class="m-0 mb-[18px] text-[14px] font-bold tracking-[0.26em] uppercase">
                {{ __('books.public.author.kicker') }}
            </p>

            <h1 class="font-display m-0 max-w-[16ch] text-[clamp(48px,6.4vw,104px)]/[0.94] font-normal tracking-[-0.01em] text-balance">
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

            <div class="mt-8 flex flex-wrap items-baseline gap-x-8 gap-y-3 text-[15px] font-semibold tracking-[0.12em] uppercase">
                <a
                    href="{{ route('home') }}"
                    class="border-b-2 border-current pb-[3px] transition-opacity duration-150 hover:opacity-65"
                >
                    {{ __('books.public.shelf_back') }}
                </a>

                {{-- Same control as the book page, and the sentence it carries
                     says whose shelf this is and which shop it is in. --}}
                <x-share-button
                    :url="route('authors.show', $author)"
                    :title="$author->name"
                    :text="$shareMessage"
                    :label="__('books.public.share.action')"
                    :copied="__('books.public.share.copied')"
                    class="text-[15px] tracking-[0.12em] opacity-75 hover:opacity-100"
                />
            </div>
        </div>

        @if ($portrait)
            <figure class="wide:mt-0 wide:w-[clamp(220px,24vw,340px)] wide:shrink-0 m-0 mt-10 w-[200px]">
                <img
                    src="{{ $portrait->getAvailableUrl(['thumb']) }}"
                    alt="{{ $author->name }}"
                    width="480"
                    height="640"
                    class="block aspect-[3/4] w-full object-cover shadow-[0_2px_8px_rgba(33,21,17,0.18),0_26px_60px_rgba(33,21,17,0.34)]"
                />

                @if ($credit)
                    <figcaption class="mt-3 text-[11px]/[1.4] tracking-[0.04em] opacity-60">
                        {{
                            __('books.public.author.portrait_credit', [
                                'artist'  => str($credit['artist'] ?? __('books.public.author.unknown_artist'))->limit(60),
                                'license' => $credit['license'] ?? '',
                            ])
                        }}
                    </figcaption>
                @endif
            </figure>
        @endif
    </section>

    <main class="bg-paper text-ink px-[clamp(22px,5vw,80px)] pt-[clamp(44px,5vw,76px)] pb-[clamp(56px,6vw,96px)]">
        <x-book-grid :books="$books" />
    </main>
</x-layouts.shelf>
