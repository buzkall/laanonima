@props(['cta' => true, 'onPhone' => true, 'book' => null])

{{-- The "ask us for it" call to action is a form now, not a mailto: the shop
     gets a row it can follow up in the panel rather than a loose email. The
     address stays on the last line for anything the form does not cover.

     Two footers, really. With the call to action it is a panel of its own and
     wants the room. Without it -- /la-cupida, the request form -- it is one line
     of small caps, and the deep padding was a strip of cream taking a fifth of a
     phone screen away from a page built to fill the window.

     `:book` is the book the page it sits under is about, and all it changes is
     where the call to action lands: on a page that already knows which book a
     reader would be asking for -- a shared recommendation -- the form should
     arrive filled in, the same way it does from the book's own page. Without one
     it is the empty form, which is what the shelf and the home page want.

     `:on-phone="false"` takes even that line off a phone. It is for a page whose
     every state is measured to fill the window on its own -- La Cupida -- where
     a cream strip under the deck is both the last of the room the cards want and
     a rule drawn under a page that has no end. The line still stands on a
     laptop, where the room is not the constraint. --}}
<footer @class([
    'border-t border-ink bg-paper px-[max(clamp(22px,5vw,80px),calc(50vw-320px))] text-center text-ink',
    'pt-[clamp(48px,5vw,80px)] pb-[clamp(56px,6vw,88px)]' => $cta,
    'py-[clamp(16px,2.5vw,26px)]'                         => ! $cta,
    'wide:block hidden'                                   => ! $onPhone,
])>
    @if ($cta)
        <p class="font-display mx-auto my-0 max-w-[620px] text-[clamp(30px,5vw,50px)]/[1.05] text-balance">
            {{ __('books.public.out_of_stock.heading') }}
        </p>
        <p class="mx-auto mt-5 mb-0 max-w-[480px] text-[20px] italic">{{ __('books.public.out_of_stock.body') }}</p>

        <a
            href="{{ $book ? route('book-requests.create.book', $book) : route('book-requests.create') }}"
            class="text-paper mt-[34px] inline-block bg-[var(--accent)] px-6 py-3 text-[18px] font-semibold tracking-[0.08em] uppercase transition-opacity duration-150 hover:opacity-85"
        >
            {{ __('books.public.out_of_stock.cta') }}
        </a>
    @endif

    <p @class([
        'mb-0 text-[14px] uppercase tracking-[0.2em] opacity-70',
        'mt-16' => $cta,
    ])>
        {{ __('books.public.footer_line', ['name' => config('app.name')]) }} ·
        <a
            href="mailto:{{ config('site.contact_email') }}"
            class="tracking-[0.2em] text-[var(--accent)] transition-opacity duration-150 hover:opacity-65"
        >{{ config('site.contact_email') }}</a>
    </p>
</footer>
