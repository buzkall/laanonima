@php
    /* Written once and rendered twice: inline from `wide:` up, and inside the
       disclosure below it. Two lists that had to be kept in step by hand is how
       a nav link ends up on a laptop and nowhere else. */
    $links = [
        [
            'url' => route('books.shelf'),
            'label' => __('books.public.shelf.title'),
            'active' => request()->routeIs('books.shelf'),
        ],
        [
            'url' => route('cupida'),
            'label' => __('cupida.nav'),
            'active' => request()->routeIs('cupida'),
        ],
    ];
@endphp

{{-- `relative` so the phone menu can hang off the bar without growing it: the
     inline script in `books/show.blade.php` measures this element into
     `--top-bar` on every paint, and a panel that pushed the header taller would
     shove the floating cover down the page every time it was opened. --}}
<header class="relative flex items-center justify-between border-b border-[var(--rule)] bg-[var(--cover)] px-[clamp(22px,4vw,44px)] py-[18px] text-[var(--on-cover)]">
    {{-- The wordmark carries its own brand colors, so unlike the rest of the
         header it does NOT recolour with --on-cover. The `wordmark` class is
         where that costs something and what it costs: the "LA" is the house
         mint, invisible on a cover painted in it, and the class multiplies the
         wordmark over the covers where that happens. --}}
    <a href="{{ route('home') }}" class="transition-opacity duration-150 hover:opacity-65">
        <img
            src="{{ Vite::asset('resources/images/brand/la-anonima-logo.png') }}"
            alt="{{ config('app.name') }}"
            width="922"
            height="242"
            class="wordmark h-[clamp(28px,4vw,36px)] w-auto"
        />
    </a>

    <div class="flex items-center gap-[clamp(16px,3vw,28px)]">
        <nav class="wide:flex hidden items-center gap-[clamp(16px,3vw,28px)]">
            @foreach ($links as $link)
                <a
                    href="{{ $link['url'] }}"
                    @class([
                        'text-[13px] font-semibold uppercase tracking-[0.22em] transition-opacity duration-150 hover:opacity-65',
                        'underline underline-offset-[6px]' => $link['active'],
                    ])
                >{{ $link['label'] }}</a>
            @endforeach
        </nav>

        {{-- The phone menu. `<details>` rather than a script because the bar is
             on every page of the site and only the shelf and La Cupida carry
             JavaScript at all: Alpine arrives with Livewire, so it is there on
             La Cupida and nowhere else, and a hamburger that works on one page
             in five is worse than none. The browser owns the open state and the
             `aria-expanded` that goes with it. --}}
        <details class="wide:hidden group">
            <summary
                aria-label="{{ __('books.public.menu') }}"
                class="flex cursor-pointer list-none items-center transition-opacity duration-150 hover:opacity-65 [&::-webkit-details-marker]:hidden"
            >
                <x-heroicon-o-bars-3 class="size-[22px] shrink-0 group-open:hidden" />
                <x-heroicon-o-x-mark class="hidden size-[22px] shrink-0 group-open:block" />
            </summary>

            {{-- Full width and hung off the bottom edge of the bar rather than
                 off the summary, so it clears the floating cover (z-30) and the
                 links land under a finger instead of in a narrow column pinned
                 to one corner. --}}
            <nav class="absolute inset-x-0 top-full z-50 flex flex-col border-b border-[var(--rule)] bg-[var(--cover)] px-[clamp(22px,4vw,44px)] py-2 shadow-lg">
                @foreach ($links as $link)
                    <a
                        href="{{ $link['url'] }}"
                        @class([
                            'py-4 text-[13px] font-semibold uppercase tracking-[0.22em] transition-opacity duration-150 hover:opacity-65',
                            'border-t border-[var(--rule)]' => ! $loop->first,
                            'underline underline-offset-[6px]' => $link['active'],
                        ])
                    >{{ $link['label'] }}</a>
                @endforeach
            </nav>
        </details>

        {{-- Clients sign in here to reach their own pages in the client panel;
             an administrator already signed in is sent to the admin panel. --}}
        <a
            href="{{ auth()->user()?->role->panelUrl() ?? \App\Enums\UserRole::Client->loginUrl() }}"
            title="{{ auth()->check() ? __('books.public.account') : __('books.public.login') }}"
            class="inline-flex items-center transition-opacity duration-150 hover:opacity-65"
        >
            <x-heroicon-o-user class="size-[22px] shrink-0" />
            <span class="sr-only">{{ auth()->check() ? __('books.public.account') : __('books.public.login') }}</span>
        </a>
    </div>
</header>
