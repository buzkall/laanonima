<header class="flex items-baseline justify-between border-b border-[var(--rule)] bg-[var(--cover)] px-[clamp(22px,4vw,44px)] py-[18px] text-[var(--on-cover)]">
    <a href="{{ route('home') }}" class="font-display text-[19px] tracking-[0.02em] transition-opacity duration-150 hover:opacity-65">{{ config('app.name') }}</a>
    <span class="text-[13px] font-semibold uppercase tracking-[0.22em]">{{ __('books.public.tagline') }}</span>
</header>
