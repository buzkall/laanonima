@php
    /* The question a round is asking, in the order CupidaDeck builds them. */
    $questions = App\Support\Cupida\CupidaDeck::ROUNDS;
    $question = $questions[$round] ?? null;
@endphp

{{-- Every state of this page is one full-bleed panel, so it takes the whole
     of what the layout leaves rather than sitting at the top of it with cream
     underneath. Each panel centers what it holds: on a laptop the answer is
     halfway down the screen, and on a phone, where the content is taller than
     the window anyway, nothing moves. --}}
<div data-cupida class="flex flex-1 flex-col">
    @if ($empty)
        <section class="flex flex-1 flex-col justify-center bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[clamp(48px,7vw,104px)] pb-[clamp(52px,6vw,96px)] text-[var(--on-cover)]">
            <h1 class="font-display m-0 max-w-[16ch] text-[clamp(48px,6.4vw,104px)]/[0.94] font-normal tracking-[-0.01em] text-balance">
                {{ __('cupida.empty.heading') }}
            </h1>

            <p class="mt-9 mb-0 max-w-[620px] border-t border-[var(--rule)] pt-8 text-[clamp(20px,2.1vw,26px)]/[1.5] italic">
                {{ __('cupida.empty.line') }}
            </p>
        </section>

    @elseif (! $started)
        {{-- The opening card: the section's own mark, what it does, and the one
         thing a reader has to understand before the deck makes sense.

         Every vertical measurement here is capped against `svh` as well as its
         own value, because the panel has to fit a phone and a phone never gives
         a page the screen. On an iPhone 17 the window is 874pt tall and the
         browser keeps around 190 of it for its bars, so a card measured in
         px alone fits the simulator exactly and drops the arrow off the bottom
         of the real thing. `svh`, not `dvh`: the small viewport is the one with
         the bars showing, so it is both the worst case and -- unlike `dvh` --
         a number that does not change while the reader scrolls, which would
         resize the type under their thumb.

         The caps are `min(px,svh)` rather than plain `svh` so a desktop, where
         there is room to spare, keeps exactly the card it already had. --}}
        <section class="flex flex-1 flex-col justify-center bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[min(clamp(28px,4vw,56px),2.4svh)] pb-[min(clamp(36px,5vw,72px),2.4svh)] wide:pt-[clamp(28px,4vw,56px)] wide:pb-[clamp(36px,5vw,72px)] text-center text-[var(--on-cover)]">
            {{-- `object-contain` is not decoration: `max-h` on a replaced element
             whose width is set squashes it, and contain letterboxes it inside
             the shorter box at its own ratio instead. --}}
            <img
                src="{{ Vite::asset('resources/images/brand/la-cupida.webp') }}"
                alt="{{ __('cupida.title') }}"
                width="760"
                height="983"
                class="wide:max-h-none mx-auto h-auto max-h-[32svh] w-[min(62vw,300px)] object-contain"
            />

            {{-- Only the floors of these two clamps are a phone decision: 4.2vw
             does not reach 44px until a 1048px window, so every handset reads
             the low number and the clamp is a laptop's business. Raising the
             floor and leaving the ceiling alone is therefore how this card is
             set on a phone without moving what a desktop already got right.

             This panel is the one screen with room to spare -- the deck fills
             its own, and the footer stopped taking a fifth of the window -- so
             the type is set to the room rather than to the smallest size that
             fits. --}}
            <p class="font-display mx-auto wide:mt-[clamp(22px,4vw,36px)] wide:pt-7 wide:text-[clamp(44px,4.2vw,52px)]/[1.06] mt-[min(clamp(22px,4vw,36px),2.4svh)] mb-0 max-w-[24ch] border-t border-[var(--rule)] pt-[min(28px,3svh)] text-[min(clamp(44px,4.2vw,52px),5.6svh)]/[1.06] text-balance">
                {{ __('cupida.start.greeting', ['name' => $greeting]) }}
            </p>

            {{-- Two lines, broken here rather than in the string: who she is, and
             then what she is about to do. The break is the sentence's own and
             not a wrap, so it holds at every width.

             Two blocks rather than one `<br />`, so each sentence balances
             against itself. On a phone the promise does not fit on one line at
             any size worth reading it at, and left to wrap it drops its last
             word alone onto a third line -- `text-balance` on the paragraph as
             a whole would weigh that orphan against the short line above it
             instead of against the sentence it belongs to. --}}
            <p class="mx-auto wide:mt-6 wide:text-[clamp(26px,2.2vw,28px)]/[1.45] mt-[min(24px,2.6svh)] mb-0 max-w-[34ch] text-[min(clamp(26px,2.2vw,28px),3.1svh)]/[1.45] italic">
                <span class="block text-balance">{{ __('cupida.start.lead') }}</span>
                <span class="block text-balance">{{ __('cupida.start.promise', ['match' => $match]) }}</span>
            </p>

            {{-- Two controls, one action, and only ever one of them on screen.

             On a phone the panel already fills the window, so a solid block of
             a button under the mark reads as the end of the page rather than
             as the way on; the arrow is the gesture everything else on a phone
             answers to. On a laptop there is room for a button that says what
             it does, and an arrow pointing down at nothing would be a lie --
             there is no page below, the deck replaces this panel in place. --}}
            <button
                type="button"
                wire:click="start"
                {{-- `self-center` because the panel is a flex column now: a button
                 left to stretch runs the whole width of the screen. --}}
                class="wide:block mt-9 hidden cursor-pointer self-center border-0 bg-[var(--on-cover)] px-8 py-4 font-serif text-[18px] font-semibold tracking-[0.08em] text-[var(--cover)] uppercase transition-opacity duration-150 hover:opacity-85"
            >
                {{ __('cupida.start.button') }}
            </button>

            <button
                type="button"
                wire:click="start"
                aria-label="{{ __('cupida.start.button') }}"
                class="cupida-nudge wide:hidden mt-[min(clamp(28px,7vw,44px),3.2svh)] cursor-pointer self-center border-0 bg-transparent p-2 text-[var(--on-cover)]"
            >
                <x-heroicon-o-chevron-down class="size-[min(40px,4.6svh)]" />
            </button>
        </section>

    @elseif ($recommendation)
        @php($palette = $recommendation->palette)

        <section
            class="flex flex-1 flex-col justify-center bg-[var(--card)] px-[clamp(22px,5vw,80px)] pt-[clamp(40px,6vw,88px)] pb-[clamp(44px,6vw,88px)] text-[var(--on-card)]"
            style="--card: {{ $palette->background }}; --on-card: {{ $palette->foreground }}"
        >
            {{-- Held to a column rather than run the width of a desktop screen: a
             pitch set across 1900 pixels is three long lines adrift in a lot of
             color, and the same words in a column are a paragraph that fills
             the panel it is standing in. --}}
            <div class="mx-auto w-full max-w-[1040px]">
                {{-- On a phone this panel is a poster: the mark of the section is
                 gone by now, so the cover is what the reader is looking at and
                 it wants the middle of the screen. From `wide:` up the grid
                 puts the cover in its own column beside the words and the
                 editorial left edge is the one that reads -- so the centring
                 is the narrow layout's, never both.

                 The rule above the match line is where it stops: head centered,
                 body left. A pitch is five lines of prose and centring those
                 costs a reader a ragged left edge to find on every one. --}}
                <p class="wide:text-left m-0 mb-[18px] text-center text-[14px] font-bold tracking-[0.26em] uppercase">
                    {{ __('cupida.result.kicker') }}
                </p>

                <div class="wide:grid-cols-[minmax(0,240px)_1fr] grid grid-cols-1 items-start gap-[clamp(28px,4vw,56px)]">
                    @if ($recommendation->coverUrl)
                        <img
                            src="{{ $recommendation->coverUrl }}"
                            alt="{{ __('books.fields.cover') }}: {{ $recommendation->title }}"
                            class="wide:mx-0 mx-auto w-[min(56vw,240px)] max-w-full shadow-[0_18px_0_-8px_rgba(33,21,17,0.18),0_28px_60px_-24px_rgba(33,21,17,0.6)]"
                        />
                    @endif

                    <div>
                        <h1 class="font-display wide:mx-0 wide:text-left m-0 mx-auto max-w-[18ch] text-center text-[clamp(38px,5.2vw,76px)]/[0.98] font-normal tracking-[-0.01em] text-balance">
                            {{ $recommendation->title }}
                        </h1>

                        @if ($recommendation->author)
                            <p class="wide:text-left mt-3 mb-0 text-center text-[clamp(22px,2vw,24px)] italic opacity-85">
                                {{ __('cupida.result.by', ['author' => $recommendation->author]) }}
                            </p>
                        @endif

                        @if ($recommendation->matchLine)
                            <p class="mt-7 mb-0 border-t border-[var(--rule)] pt-6 text-[14px] font-bold tracking-[0.16em] uppercase">
                                {{ $recommendation->matchLine }}
                            </p>
                        @endif

                        <p @class([
                        'mb-0 max-w-[52ch] text-[clamp(22px,2.1vw,25px)]/[1.5] italic',
                        'mt-5'                                    => $recommendation->matchLine,
                        'mt-7 border-t border-[var(--rule)] pt-6' => ! $recommendation->matchLine,
                                            ])>
                            {{ $recommendation->pitch }}
                        </p>

                        <div class="mt-9 flex flex-wrap items-center gap-4">
                            <a
                                href="{{ $recommendation->url }}"
                                @if (! $recommendation->book) target="_blank" rel="noopener" @endif
                                class="bg-[var(--on-card)] px-6 py-3 text-[18px] font-semibold tracking-[0.08em] text-[var(--card)] uppercase no-underline transition-opacity duration-150 hover:opacity-85"
                            >
                                {{ $recommendation->book ? __('cupida.result.read_more') : __('cupida.result.buy') }}
                            </a>

                            <button
                                type="button"
                                wire:click="restart"
                                class="cursor-pointer border-0 border-b-2 border-current bg-transparent pb-[3px] font-serif text-[18px] font-semibold tracking-[0.08em] text-[var(--on-card)] uppercase transition-opacity duration-150 hover:opacity-65"
                            >
                                {{ __('cupida.result.again') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    @elseif ($thinking)
        {{-- wire:init is what makes the wait bearable: the swipe that ended the
         third round already returned, so this panel is on screen before
         anything waits on a model. --}}
        <section
            wire:init="recommend"
            class="wide:py-[clamp(48px,7vw,104px)] flex flex-1 flex-col items-center justify-center bg-[var(--cover)] px-[clamp(22px,5vw,80px)] py-[min(clamp(48px,7vw,104px),2.6svh)] text-center text-[var(--on-cover)]"
        >
            {{-- The same mark the opening card opens with, smaller and breathing:
             the wait is the one screen with nothing on it to look at, and a
             reader who has just answered eighteen cards should be able to see
             that something is still going on. Decorative -- the heading below
             already says what is happening -- so it carries no alt text. --}}
            <img
                src="{{ Vite::asset('resources/images/brand/la-cupida.webp') }}"
                alt=""
                width="760"
                height="983"
                class="cupida-float wide:mb-[clamp(26px,4vw,42px)] wide:max-h-none mb-[min(clamp(26px,4vw,42px),2.4svh)] h-auto max-h-[32svh] w-[min(62vw,300px)] object-contain"
            />

            <span class="cupida-pulse font-display wide:text-[clamp(44px,5vw,68px)]/[1.05] text-[min(clamp(44px,5vw,68px),5.4svh)]/[1.05]">
                {{ __('cupida.thinking.heading') }}
            </span>

            {{-- Set to the same floor as the opening card's two lines, which is
             the panel this one is the other half of: both are the mark, a line
             of display type and one italic sentence, and a reader who has just
             met one at 26px should not find the other at 18px. --}}
            <p class="wide:mt-6 wide:text-[clamp(26px,2.2vw,28px)]/[1.45] mt-[min(24px,2.6svh)] mb-0 max-w-[38ch] text-[min(clamp(26px,2.2vw,28px),3.1svh)]/[1.45] text-balance italic opacity-80">
                {{ __('cupida.thinking.line') }}
            </p>
        </section>

    @else
        <section class="wide:pt-[clamp(32px,5vw,72px)] wide:pb-[clamp(28px,4vw,56px)] bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[min(clamp(32px,5vw,72px),3svh)] pb-[min(clamp(28px,4vw,56px),2.2svh)] text-[var(--on-cover)]">
            <p class="wide:mb-[14px] m-0 mb-[min(14px,1.6svh)] text-[14px] font-bold tracking-[0.26em] uppercase">
                {{ __('cupida.progress', ['current' => $round + 1, 'total' => $rounds]) }}
            </p>

            <h1 class="font-display wide:text-[clamp(40px,5.6vw,84px)]/[0.96] m-0 max-w-[14ch] text-[min(clamp(40px,5.6vw,84px),4.4svh)]/[0.96] font-normal tracking-[-0.01em] text-balance">
                {{ __("cupida.questions.{$question}") }}
            </h1>
        </section>

        <main class="bg-paper text-ink wide:pt-[clamp(28px,4vw,52px)] wide:pb-[clamp(40px,5vw,80px)] flex flex-1 flex-col justify-center px-[clamp(22px,5vw,80px)] pt-[min(clamp(28px,4vw,52px),1.8svh)] pb-[min(clamp(40px,5vw,80px),2.2svh)]">
            <div
                class="mx-auto flex w-full max-w-[420px] min-h-0 flex-1 flex-col justify-center"
                x-data="cupidaDeck()"
                @keydown.window.arrow-left.prevent="answer(false)"
                @keydown.window.arrow-right.prevent="answer(true)"
            >
                {{-- Three cards, not the whole round. Six all peeking below one
                 another reads as a color chart rather than as a stack, and
                 nothing under the third is ever seen.

                 Drawn back to front so the first card is on top without any
                 z-index bookkeeping: later siblings paint over earlier ones,
                 and `depth` is counted from the end. --}}
                @php($stack = array_slice($cards, 0, 3))

                {{-- The room the deck gets, and the reason it is a flex column
                 rather than a wrapper with a height on it. Nothing above this
                 has a definite height -- the layout's shell is `min-h-dvh`, a
                 minimum, so a percentage height resolves to `auto` and an
                 aspect box collapses to nothing. A flex item with `flex-1` in a
                 column does have a definite main size, and `aspect-[3/4]`
                 transfers it to the width: the deck is exactly as big as what
                 is left between the question and the buttons, at every window
                 height, with no measuring script and no guess at a fraction of
                 the viewport.

                 `items-center` matters: the default `stretch` would set the
                 width itself and the ratio would have nothing to say.

                 `wide:min-h-[560px]` is what keeps a laptop's card the size it
                 has always been -- from `wide:` up this panel is allowed to run
                 past the fold, as it always has, rather than crushing the deck
                 to fit a short window. --}}
                <div class="flex min-h-0 flex-1 flex-col items-center">
                    {{-- The gesture is bound here rather than on the card. Alpine
                     binds `@pointerdown` and registers `x-ref` when it initialises
                     an element, and Livewire's morph patches attributes onto
                     elements that are already initialised -- so a card promoted to
                     the top by a re-render never gets either, and every swipe after
                     the first silently does nothing. The stack is the same element
                     all the way through, so binding here always works and the
                     script asks the DOM which card is on top. --}}
                    <div
                        class="cupida-stack wide:min-h-[560px] relative aspect-[3/4] max-h-[560px] w-auto max-w-full min-h-0 flex-1"
                        @pointerdown="grab($event)"
                        @pointermove="drag($event)"
                        @pointerup="release()"
                        @pointercancel="release()"
                    >
                        @foreach (array_reverse($stack) as $index => $card)
                            @php($depth = count($stack) - 1 - $index)

                            <article
                                wire:key="{{ $card->answer() }}"
                                data-depth="{{ $depth }}"
                                @class([
                                'cupida-card wide:p-[clamp(22px,6vw,34px)] absolute inset-0 flex flex-col justify-between p-[min(clamp(22px,6vw,34px),3svh)]',
                                'cupida-card--top' => $depth === 0,
                                                        ])
                                style="--card: {{ $card->palette->background }}; --on-card: {{ $card->palette->foreground }}; --depth: {{ $depth }}"
                            >
                                <p class="wide:text-[13px] m-0 text-[min(13px,1.9svh)] font-bold tracking-[0.22em] uppercase opacity-70">
                                    {{ __("cupida.kinds.{$card->kind}") }}
                                </p>

                                {{-- A face, where we have one. Only about four author
                                 cards in six carry a portrait, so this has to be
                                 absent rather than a placeholder: a silhouette
                                 among real faces reads as a missing image, and the
                                 card without one is a complete card already.

                                 Eager, not lazy: three cards are ever in the stack,
                                 and a portrait that fades in as the reader is
                                 already dragging is worse than one that is simply
                                 there. The background is the portrait's own color
                                 so the frame is the right color before it paints. --}}
                                @if ($card->portrait)
                                    <img
                                        src="{{ $card->portrait->url() }}"
                                        alt="{{ $card->label }}"
                                        width="480"
                                        height="640"
                                        loading="eager"
                                        decoding="async"
                                        class="wide:max-h-none mx-auto max-h-[17svh] w-[58%] rounded-[3px] object-cover shadow-[0_8px_0_-5px_rgba(33,21,17,0.16),0_18px_36px_-18px_rgba(33,21,17,0.55)]"
                                        @style(['background: ' . $card->portrait->color => filled($card->portrait->color)])
                                    />
                                @endif

                                <div>
                                    <h2 class="font-display wide:text-[clamp(30px,8vw,46px)]/[1.02] m-0 text-[min(clamp(30px,8vw,46px),4.2svh)]/[1.02] font-normal text-balance">
                                        {{ $card->label }}
                                    </h2>

                                    @if ($card->note)
                                        <p class="wide:mt-3 wide:text-[17px]/[1.35] mt-[min(12px,1.8svh)] mb-0 text-[min(17px,2.4svh)]/[1.35] italic opacity-75">{{ $card->note }}</p>
                                    @endif
                                </div>

                                @if ($depth === 0)
                                    <span
                                        class="cupida-stamp cupida-stamp--like"
                                        aria-hidden="true"
                                    >{{ __('cupida.swipe.like') }}</span>
                                    <span
                                        class="cupida-stamp cupida-stamp--pass"
                                        aria-hidden="true"
                                    >{{ __('cupida.swipe.pass') }}</span>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>

                {{-- The buttons are the real control, not a fallback: they are what
                 a keyboard and a screen reader get, and what the tests press.
                 The drag is decoration over the top of them. --}}
                <div class="wide:mt-[clamp(22px,4vw,34px)] mt-[min(clamp(22px,4vw,34px),1.8svh)] flex items-center justify-center gap-[clamp(20px,6vw,40px)]">
                    <button
                        type="button"
                        @click="answer(false)"
                        class="cupida-button"
                        aria-label="{{ __('cupida.swipe.pass') }}"
                    >
                        <x-heroicon-o-x-mark class="size-8 shrink-0" />
                    </button>

                    <button
                        type="button"
                        @click="answer(true)"
                        class="cupida-button cupida-button--like"
                        aria-label="{{ __('cupida.swipe.like') }}"
                    >
                        <x-heroicon-o-heart class="size-8 shrink-0" />
                    </button>
                </div>

                {{-- Hidden below `wide:`. Half of what it says is about arrow keys, which a
                 phone does not have, and the other half describes a gesture the
                 stack already invites -- and it is the one line on this screen
                 that can go without costing a control. --}}
                <p class="wide:block mt-6 mb-0 hidden text-center text-[16px] italic opacity-60">{{ __('cupida.swipe.help') }}</p>

                {{-- Who took the photo on the card in front of the reader.

                 Under the buttons rather than on the card itself: on the card it
                 has to wrap inside 58% of the width and lands on top of the
                 writer's name, and it would fly off with the card mid-swipe.
                 Out here it has the full column, and it is outside
                 `.cupida-stack`, so it can never swallow a drag.

                 `$stack[0]` is the top card -- the loop above reverses the
                 stack to paint the deepest card first. The word for "cropped"
                 is the changes-were-made clause CC BY-SA asks for: every
                 portrait is recropped to the card. --}}
                @if (($stack[0] ?? null)?->portrait?->license)
                    <p class="mx-auto mt-3 mb-0 max-w-[34em] text-center text-[10px]/[1.4] tracking-[0.04em] text-balance opacity-45">
                        {{
                            __('cupida.portraits.credit', [
                            'artist'  => $stack[0]->portrait->artistLabel() ?: __('cupida.portraits.unknown_artist'),
                            'license' => $stack[0]->portrait->license,
                            ])
                        }}
                    </p>
                @endif
            </div>
        </main>

    @endif
</div>
