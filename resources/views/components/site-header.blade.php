<header class="flex items-center justify-between border-b border-[var(--rule)] bg-[var(--cover)] px-[clamp(22px,4vw,44px)] py-[18px] text-[var(--on-cover)]">
    {{-- The wordmark carries its own brand colours, so unlike the rest of the
         header it does NOT recolour with --on-cover. --}}
    <a href="{{ route('home') }}" class="transition-opacity duration-150 hover:opacity-65">
        <img
            src="{{ Vite::asset('resources/images/brand/la-anonima-logo.png') }}"
            alt="{{ config('app.name') }}"
            width="922"
            height="242"
            class="h-[clamp(28px,4vw,36px)] w-auto"
        >
    </a>

    <div class="flex items-center gap-[clamp(16px,3vw,28px)]">
        <a
            href="{{ route('books.shelf') }}"
            @class([
                'text-[13px] font-semibold uppercase tracking-[0.22em] transition-opacity duration-150 hover:opacity-65',
                'underline underline-offset-[6px]' => request()->routeIs('books.shelf'),
            ])
        >{{ __('books.public.shelf.title') }}</a>

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
