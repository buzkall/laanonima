<header class="flex items-baseline justify-between border-b border-[var(--rule)] bg-[var(--cover)] px-[clamp(22px,4vw,44px)] py-[18px] text-[var(--on-cover)]">
    <a href="{{ route('home') }}" class="font-display text-[19px] tracking-[0.02em] transition-opacity duration-150 hover:opacity-65">{{ config('app.name') }}</a>

    <div class="flex items-center gap-[clamp(16px,3vw,28px)]">
        <a
            href="{{ route('books.shelf') }}"
            @class([
                'text-[13px] font-semibold uppercase tracking-[0.22em] transition-opacity duration-150 hover:opacity-65',
                'underline underline-offset-[6px]' => request()->routeIs('books.shelf'),
            ])
        >{{ __('books.public.shelf.title') }}</a>

        <span class="hidden text-[13px] font-semibold uppercase tracking-[0.22em] sm:inline">{{ __('books.public.tagline') }}</span>

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
