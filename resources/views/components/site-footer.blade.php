<footer class="border-t border-ink bg-paper px-[max(clamp(22px,5vw,80px),calc(50vw-320px))] pt-[clamp(48px,5vw,80px)] pb-[clamp(56px,6vw,88px)] text-center text-ink">
    <p class="mx-auto my-0 max-w-[620px] text-balance font-display text-[clamp(30px,5vw,50px)]/[1.05]">{{ __('books.public.out_of_stock.heading') }}</p>
    <p class="mx-auto mt-5 mb-0 max-w-[480px] text-[20px] italic">{{ __('books.public.out_of_stock.body') }}</p>

    <a
        href="mailto:{{ config('site.contact_email') }}"
        class="mt-[34px] inline-block bg-[var(--accent)] px-6 py-3 text-[18px] font-semibold uppercase tracking-[0.08em] text-paper transition-opacity duration-150 hover:opacity-85"
    >
        {{ __('books.public.out_of_stock.cta') }}
    </a>

    <p class="mt-16 mb-0 text-[14px] uppercase tracking-[0.2em] opacity-70">
        {{ __('books.public.footer_line', ['name' => config('app.name')]) }} ·
        <a href="mailto:{{ config('site.contact_email') }}" class="tracking-[0.2em] text-[var(--accent)] transition-opacity duration-150 hover:opacity-65">{{ config('site.contact_email') }}</a>
    </p>
</footer>
