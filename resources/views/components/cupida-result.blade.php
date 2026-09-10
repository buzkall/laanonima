@props(['recommendation', 'kicker', 'clamp' => true])

{{--
 | The result: one book, and what La Cupida wrote about it.
 |
 | Drawn in two places -- the Livewire panel at the end of a session, and the
 | page that panel's share button sends somebody -- which is the whole reason it
 | is a component. The layout in here is not obvious and it is not cheap: the
 | three folded areas of `.cupida-result`, the clamped `.cupida-pitch` and the
 | synopsis that rides the same disclosure are all documented in
 | `.ai/rules/cupida.md`, and two copies of it would have drifted by the second
 | change.
 |
 | The controls are a slot rather than a row of flags, because that is the only
 | part the two callers genuinely disagree about: the panel offers another go,
 | the shared page invites the reader to have their own.
 |
 | `:clamp="false"` is the shared page. That page is a document with nothing to
 | fit, so the pitch and the synopsis are simply open and no Alpine runs at all
 | -- rather than a disclosure that starts open and can be shut for no reason.
 --}}
<p class="mb-[18px] m-0 text-[14px] font-bold tracking-[0.26em] uppercase">
    {{ $kicker }}
</p>

<div @class(['cupida-result', 'cupida-result--no-cover' => ! $recommendation->coverUrl])>
    @if ($recommendation->coverUrl)
        <img
            src="{{ $recommendation->coverUrl }}"
            alt="{{ __('books.fields.cover') }}: {{ $recommendation->title }}"
            class="cupida-result__cover wide:w-[min(56vw,240px)] wide:shadow-[0_18px_0_-8px_rgba(33,21,17,0.18),0_28px_60px_-24px_rgba(33,21,17,0.6)] w-full max-w-full shadow-[0_7px_0_-4px_rgba(33,21,17,0.18),0_12px_26px_-12px_rgba(33,21,17,0.6)]"
        />
    @endif

    {{-- The band the cover shares on a phone: which book it is, and
     nothing else. Everything that is prose waits below. --}}
    <div class="cupida-result__head">
        <h1 class="font-display text-[clamp(38px,5.2vw,76px)]/[0.98] m-0 max-w-[18ch] font-normal tracking-[-0.01em] text-balance">
            {{ $recommendation->title }}
        </h1>

        @if ($recommendation->author)
            <p class="mt-3 text-[clamp(22px,2vw,24px)] mb-0 italic opacity-85">
                {{ __('cupida.result.by', ['author' => $recommendation->author]) }}
            </p>
        @endif
    </div>

    <div class="cupida-result__body" @if ($clamp) x-data="{ open: false }" @endif>
        @if ($recommendation->matchLine)
            <p class="wide:mt-7 pt-6 text-[14px]/[1.65] mt-0 mb-0 border-t border-[var(--rule)] font-bold tracking-[0.16em] uppercase">
                {{ $recommendation->matchLine }}
            </p>
        @endif

        {{-- Three lines on a phone, all of it from `wide:` up. The
         clamp lives in the stylesheet and Alpine only opens it,
         so the fallback is a paragraph that reads short rather
         than one that never opens. --}}
        <p
            @if ($clamp) :class="open && 'cupida-pitch--open'" @endif
            @class([
            'text-[clamp(22px,2.1vw,25px)]/[1.5] mb-0 max-w-[52ch] italic',
            'cupida-pitch'                              => $clamp,
            'mt-5'                                      => $recommendation->matchLine,
            'wide:mt-7 pt-6 mt-0 border-t border-[var(--rule)]' => ! $recommendation->matchLine,
                                    ])
        >
            {{ $recommendation->pitch }}
        </p>

        <button
            @if (! $clamp) hidden @endif
            type="button"
            x-show="! open"
            @click="open = true"
            class="wide:hidden mt-[8px] cursor-pointer border-0 border-b border-current bg-transparent p-0 pb-[2px] font-serif text-[15px] font-semibold tracking-[0.08em] text-[var(--on-card)] uppercase opacity-70 transition-opacity duration-150 hover:opacity-100"
        >
            {{ __('cupida.result.more') }}
        </button>

        {{-- The shop's own description of the book, under the
         librera's. It is the other half of deciding: the pitch
         is somebody telling you to read this, and this is what
         it is about, in the catalog's words rather than hers.

         It rides the same `open` as the pitch instead of
         bringing a second control -- "Seguir leyendo" already
         means "there is more of this book below", and two
         disclosures stacked on a phone are two decisions where
         the reader wanted one. Hidden by CSS and revealed by
         the class, the way the pitch is clamped, so the failure
         is a panel that reads short rather than a dead button;
         from `wide:` up it is simply there. --}}
        @if ($recommendation->synopsis)
            <div
                @if ($clamp) :class="open && 'cupida-synopsis--open'" @endif
                @class(['mt-8 border-t border-[var(--rule)] pt-6', 'cupida-synopsis' => $clamp])
            >
                <p class="m-0 text-[13px] font-bold tracking-[0.22em] uppercase opacity-60">
                    {{ __('cupida.result.synopsis') }}
                </p>

                <p class="mt-3 mb-0 max-w-[60ch] text-[16px]/[1.6] opacity-80">
                    {{ $recommendation->synopsis }}
                </p>
            </div>
        @endif

        <div class="mt-9 gap-4 flex flex-wrap items-center">
            {{ $slot }}
        </div>

        {{-- The old site, kept and demoted. The button above is
         our own page for the book now -- La Cupida files what
         it recommends -- but the shop's page is where a reader
         who knows the site expects to end up, and it is the one
         that takes an order today. A line of text rather than a
         second button: two boxes of equal weight ask a question
         nobody came here to answer. --}}
        @if ($recommendation->book)
            <p class="mt-5 mb-0 text-[15px]">
                <a
                    href="{{ $recommendation->shopUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="border-0 border-b border-current pb-[2px] font-serif font-semibold tracking-[0.08em] text-[var(--on-card)] uppercase no-underline opacity-70 transition-opacity duration-150 hover:opacity-100"
                >
                    {{ __('cupida.result.at_the_shop') }}
                </a>
            </p>
        @endif
    </div>
</div>
