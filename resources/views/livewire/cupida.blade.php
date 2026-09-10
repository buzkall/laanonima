@php
    /* The question a round is asking, in the order CupidaDeck builds them. */
    $questions = App\Support\Cupida\CupidaDeck::ROUNDS;
    $question = $questions[$round] ?? null;

    /* The sentence that travels with the shared link, so what lands in
       somebody's chat says where the book came from rather than being a bare
       address. It is worked out up here and not beside the button on purpose:
       the result panel opens a one-line PHP directive of the parenthesised
       kind, and Blade pairs the next block-form close with that one instead --
       so a second block down there stops compiling and prints itself. */
    $shareMessage = $recommendation === null ? null : ($recommendation->author
        ? __('cupida.result.share_message_by', [
            'title'  => $recommendation->title,
            'author' => $recommendation->author,
        ])
        : __('cupida.result.share_message', ['title' => $recommendation->title]));
@endphp

{{-- Every state of this page is one full-bleed panel, so it takes the whole
     of what the layout leaves rather than sitting at the top of it with cream
     underneath. Each panel centers what it holds: on a laptop the answer is
     halfway down the screen, and on a phone, where the content is taller than
     the window anyway, nothing moves. --}}
<div data-cupida class="flex min-h-0 flex-1 flex-col">
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

         Nothing here is measured against the window. The panel is a flex column
         inside a shell with a real height (`fits-viewport` on the layout), so
         the type is set at the size it was designed at and the mark is simply
         allowed to give way: `min-h-0` is what lets a flex item shrink past its
         own content, and `object-contain` is what keeps it from being squashed
         while it does. It never grows beyond its natural size, because it is
         `flex: 0 1 auto` -- it can lose height, not gain it -- so a laptop and a
         tall phone get exactly the card that was drawn. --}}
        {{-- Enter starts the deck, the way the arrow keys answer it once it is
         running: the two controls below are one action, and a reader on a
         laptop should not have to reach for the pointer to take it. `.prevent`
         is what keeps the shortcut from firing twice when the button itself
         happens to hold focus -- Enter on a focused button raises its click as
         the keydown's default action. --}}
        <section
            x-data
            @keydown.window.enter.prevent="$wire.start()"
            class="flex min-h-0 flex-1 flex-col justify-center overflow-y-auto bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[clamp(28px,4vw,56px)] pb-[clamp(36px,5vw,72px)] text-center text-[var(--on-cover)]"
        >
            {{-- `object-contain` is not decoration: `max-h` on a replaced element
             whose width is set squashes it, and contain letterboxes it inside
             the shorter box at its own ratio instead. --}}
            <img
                src="{{ Vite::asset('resources/images/brand/la-cupida.webp') }}"
                alt="{{ __('cupida.title') }}"
                width="788"
                height="983"
                class="mx-auto h-auto min-h-0 w-[min(62vw,300px)] object-contain"
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
            <p class="font-display mx-auto mt-[clamp(22px,4vw,36px)] mb-0 max-w-[24ch] pt-7 text-[clamp(44px,4.2vw,52px)]/[1.06] text-balance">
                {{ __('cupida.start.greeting', ['name' => $greeting]) }}
            </p>

            {{-- One sentence per line, broken here rather than in the string:
             who she is, then how the deck works, then what she is about to
             hand over. Every break is a full stop of the copy's own and not a
             wrap, so it holds at every width, and the promise can be reworded
             in `lang/` without the view having to be told about it.

             A block per sentence rather than `<br />`, so each balances
             against itself. Below the measure a sentence still wraps -- a
             phone has no width to give it -- and `text-balance` on the
             paragraph as a whole would weigh that wrapped line against the
             short sentence above it instead of against the sentence it
             belongs to.

             The measure is cut to the longest sentence either language has
             (65 characters in Spanish, 66 in English once the longest match
             word is substituted) so that on a laptop no sentence wraps and
             the full stops are the only breaks on screen. A sentence written
             longer than that will wrap rather than overflow, but it will have
             left this shape behind -- widen the measure with it. --}}
            <p class="mx-auto mt-6 mb-0 max-w-[66ch] text-[clamp(26px,2.2vw,28px)]/[1.45] italic">
                @foreach (\Illuminate\Support\Str::of(__('cupida.start.lead').' '.__('cupida.start.promise', ['match' => $match]))->split('/(?<=\.)\s+/') as $sentence)
                    <span class="block text-balance">{{ $sentence }}</span>
                @endforeach
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
                class="cupida-nudge wide:hidden mt-[clamp(28px,7vw,44px)] cursor-pointer self-center border-0 bg-transparent p-2 text-[var(--on-cover)]"
            >
                <x-heroicon-o-chevron-down class="size-10" />
            </button>
        </section>

    @elseif ($recommendation)
        @php($palette = $recommendation->palette)

        {{-- Centered with `my-auto` on the column rather than `justify-center`
         here, and the difference only shows once "Seguir leyendo" is pressed.
         This panel is the one screen that can grow after it is painted: the
         pitch unclamps and the synopsis appears, and on a phone the result is
         taller than the window. A flex item centered by `justify-content` in a
         scroll container overflows *both* ends and the scroller cannot reach
         backwards past its own start, so the top of the panel -- the title of
         the book it just recommended -- was cut off and unreachable. An auto
         margin resolves to zero the moment the free space runs out, so the
         column is centered while it fits and top-aligned the instant it does
         not, which is the behavior this panel wanted in both states. --}}
        <section
            class="flex min-h-0 flex-1 flex-col overflow-y-auto bg-[var(--card)] px-[clamp(22px,5vw,80px)] pt-[clamp(40px,6vw,88px)] pb-[clamp(44px,6vw,88px)] text-[var(--on-card)]"
            style="--card: {{ $palette->background }}; --on-card: {{ $palette->foreground }}"
        >
            {{-- Held to a column rather than run the width of a desktop screen: a
             pitch set across 1900 pixels is three long lines adrift in a lot of
             color, and the same words in a column are a paragraph that fills
             the panel it is standing in. --}}
            <div class="mx-auto my-auto w-full max-w-[1040px]">
                {{-- The editorial left edge, at every width. This panel used to be
                 a centered poster on a phone -- cover in the middle, title
                 under it -- and it cost 1240px of a 540px screen. It is now the
                 same three areas the desktop has, folded differently
                 (`.cupida-result` in `resources/css/cupida.css`), and a folded
                 layout with a centered heading over a left-aligned body has two
                 left edges and reads as neither. --}}
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

                    <div class="cupida-result__body" x-data="{ open: false }">
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
                            :class="open && 'cupida-pitch--open'"
                            @class([
                            'cupida-pitch text-[clamp(22px,2.1vw,25px)]/[1.5] mb-0 max-w-[52ch] italic',
                            'mt-5'                                      => $recommendation->matchLine,
                            'wide:mt-7 pt-6 mt-0 border-t border-[var(--rule)]' => ! $recommendation->matchLine,
                                                    ])
                        >
                            {{ $recommendation->pitch }}
                        </p>

                        <button
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
                            <div :class="open && 'cupida-synopsis--open'" class="cupida-synopsis mt-8 border-t border-[var(--rule)] pt-6">
                                <p class="m-0 text-[13px] font-bold tracking-[0.22em] uppercase opacity-60">
                                    {{ __('cupida.result.synopsis') }}
                                </p>

                                <p class="mt-3 mb-0 max-w-[60ch] text-[16px]/[1.6] opacity-80">
                                    {{ $recommendation->synopsis }}
                                </p>
                            </div>
                        @endif

                        <div class="mt-9 gap-4 flex flex-wrap items-center">
                            <a
                                href="{{ $recommendation->url }}"
                                @if (! $recommendation->book) target="_blank" rel="noopener" @endif
                                class="bg-[var(--on-card)] px-6 py-3 text-[18px] font-semibold tracking-[0.08em] text-[var(--card)] uppercase no-underline transition-opacity duration-150 hover:opacity-85"
                            >
                                {{ $recommendation->book ? __('cupida.result.read_more') : __('cupida.result.buy') }}
                            </a>

                            {{-- The rule stays under the word alone. An icon
                                 sitting on the same underline reads as a
                                 second, empty letter of it -- so the border
                                 moves onto the label and the glyph travels
                                 beside it, the way the share control's does. --}}
                            <button
                                type="button"
                                wire:click="restart"
                                class="text-[18px] inline-flex cursor-pointer items-center gap-2 border-0 bg-transparent font-serif font-semibold tracking-[0.08em] text-[var(--on-card)] uppercase transition-opacity duration-150 hover:opacity-65"
                            >
                                <svg class="size-[1.1em] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                                </svg>

                                <span class="border-b-2 border-current pb-[3px]">
                                    {{ __('cupida.result.again') }}
                                </span>
                            </button>

                            {{-- A reader who has just been handed a book wants
                                 to tell somebody, and the panel is the only
                                 place that book exists for them -- the page
                                 keeps no session a link could reopen, so what
                                 travels is `url`: our page for the book when it
                                 is filed, the shop's when it is not. Lightest
                                 of the three controls, and outside `wire:` on
                                 purpose: it must not cost a round trip. --}}
                            <x-share-button
                                :url="$recommendation->url"
                                :title="$recommendation->title"
                                :text="$shareMessage"
                                :label="__('cupida.result.share')"
                                :copied="__('cupida.result.share_copied')"
                                class="text-[18px] text-[var(--on-card)] opacity-70 hover:opacity-100"
                            />
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
            </div>
        </section>

    @elseif ($thinking)
        {{-- wire:init is what makes the wait bearable: the swipe that ended the
         third round already returned, so this panel is on screen before
         anything waits on a model. --}}
        <section
            wire:init="recommend"
            class="flex min-h-0 flex-1 flex-col items-center justify-center overflow-y-auto bg-[var(--cover)] px-[clamp(22px,5vw,80px)] py-[clamp(48px,7vw,104px)] text-center text-[var(--on-cover)]"
        >
            {{-- The same mark the opening card opens with, smaller and breathing:
             the wait is the one screen with nothing on it to look at, and a
             reader who has just answered eighteen cards should be able to see
             that something is still going on. Decorative -- the heading below
             already says what is happening -- so it carries no alt text. --}}
            <img
                src="{{ Vite::asset('resources/images/brand/la-cupida.webp') }}"
                alt=""
                width="788"
                height="983"
                class="cupida-float mb-[clamp(26px,4vw,42px)] h-auto min-h-0 w-[min(62vw,300px)] object-contain"
            />

            <span class="cupida-pulse font-display text-[clamp(44px,5vw,68px)]/[1.05]">
                {{ __('cupida.thinking.heading') }}
            </span>

            {{-- Set to the same floor as the opening card's two lines, which is
             the panel this one is the other half of: both are the mark, a line
             of display type and one italic sentence, and a reader who has just
             met one at 26px should not find the other at 18px. --}}
            <p class="mt-6 text-[clamp(26px,2.2vw,28px)]/[1.45] mb-0 max-w-[38ch] text-balance italic opacity-80">
                {{ __('cupida.thinking.line') }}
            </p>
        </section>

    @else
        <section class="shrink-0 bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[clamp(32px,5vw,72px)] pb-[clamp(28px,4vw,56px)] text-[var(--on-cover)]">
            {{-- Below `wide:` the counter rides in the heading's own row,
             right-aligned, rather than on a line of its own: the label line
             plus its margin is 28px of the band, and the band is the one thing
             on the screen competing with the cards for height. The words go
             with it -- "Pregunta 1 de 3" does not fit beside a question, and a
             reader who has just answered one card does not need telling twice
             what the number counts.

             The heading comes first in the DOM and the counter reads after
             it, which is the order it is spoken in; `wide:order-first` is what
             lifts the label back above the heading from `wide:` up. Doing it
             the other way round -- counter first, `flex-row-reverse` to put it
             on the right -- is a row whose painted order disagrees with its
             source, and it laid out un-reversed on iOS.

             The negative margin hangs the counter half a gutter into the
             section's own padding, out of the question's way, where it reads
             as a note in the margin rather than as part of the line. The
             `-0.26em` is the tracking's trailing space, which would otherwise
             leave the last digit short of where the eye puts the edge. --}}
            <div class="wide:flex-col wide:items-stretch wide:gap-0 flex items-baseline justify-between gap-[16px]">
                {{-- One line on a phone, and that is what the third term of the
                 `min()` buys. Every line this heading wraps to is a line the
                 deck below does not get: `main` is `flex-1` and the stack is
                 sized from what is left over, so the question band is the one
                 thing on the screen competing with the cards for height.

                 The term is the width of the column divided by the longest
                 question there is in ems of this font, which is a number only
                 the locale knows: `--cupida-question-em`, set per language in
                 `resources/css/cupida.css`, where it is measured and
                 explained.

                 The column is the window less the section's own padding,
                 which is why the `max(22px,5vw)` from
                 `px-[clamp(22px,5vw,80px)]` is repeated here -- the clamp's
                 ceiling never binds below `wide:`. The counter shares that
                 column, so what it takes comes off too: 28px of digits and a
                 16px gap, less the half gutter it hangs back into the
                 padding, is a little under 30px on a phone. 32px, rounded up,
                 is the figure at every width where this term is the one that
                 wins.

                 A question longer than that wraps rather than overflows,
                 which is the failure worth having: `whitespace-nowrap` would
                 push it off the side of the screen and give the page a
                 horizontal scroll.

                 `max-w-none` is part of it. The 14ch cap is what makes a
                 desktop heading break into two good lines, and it would force
                 this one to wrap however small the type got. --}}
                <h1 class="font-display wide:max-w-[14ch] wide:text-[clamp(40px,5.6vw,84px)]/[0.96] m-0 max-w-none text-[min(clamp(40px,5.6vw,84px),calc((100vw-2*max(22px,5vw)-32px)/var(--cupida-question-em)))]/[0.96] font-normal tracking-[-0.01em] text-balance">
                    {{ __("cupida.questions.{$question}") }}
                </h1>

                <p class="wide:order-first wide:mb-[14px] wide:mr-0 m-0 mr-[calc(-0.26em-max(22px,5vw)/2)] shrink-0 text-[14px] font-bold tracking-[0.26em] uppercase">
                    <span class="wide:hidden">
                        {{ __('cupida.progress_short', ['current' => $round + 1, 'total' => $rounds]) }}
                    </span>

                    <span class="wide:inline hidden">
                        {{ __('cupida.progress', ['current' => $round + 1, 'total' => $rounds]) }}
                    </span>
                </p>
            </div>
        </section>

        {{-- The bottom padding is two numbers, not one clamp. Below `wide:` the
         footer is off the page entirely (`:footer-on-phone="false"`) and this
         panel is measured to fill the window, so every pixel under the credit
         line is cream the deck is not using -- and the deck is the one thing
         here whose size is whatever is left over, so that padding comes
         straight off the portrait. `pb-3` is the smallest gap that still reads
         as a margin rather than a crop. From `wide:` up the footer is back and
         the panel is a document again, so the padding it was drawn with
         stands. --}}
        <main class="bg-paper text-ink wide:pb-[clamp(40px,5vw,80px)] flex min-h-0 flex-1 flex-col justify-center px-[clamp(22px,5vw,80px)] pt-[clamp(28px,4vw,52px)] pb-3">
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

                 `max-h-[560px]` is the size the card was drawn at -- a ceiling,
                 not a fit, and the deck reaches it the moment a window has the
                 room. `wide:min-h-[560px]` is the same number as a floor, and it
                 belongs to the half of the page that is a document: from `wide:`
                 up the shell has no height, the question band is set at 5.6vw
                 and can take most of a laptop window on its own, and a deck free
                 to shrink there would shrink to nothing rather than let the page
                 do what it has always done and scroll.

                 `wide:flex-none` on the wrapper is what keeps the buttons under
                 the card rather than under the leftover room. Once the deck is
                 at its drawn 560px the growth buys nothing -- the ceiling
                 already binds -- and every pixel the wrapper went on absorbing
                 pushed the controls further down a window that had room to
                 spare. Sized to the deck, it hands that room back to the
                 column, whose `justify-center` then centers the card and its
                 buttons as the one group they read as. Below `wide:` the growth
                 is the whole mechanism and stays. --}}
                <div class="wide:flex-none flex min-h-0 flex-1 flex-col items-center">
                    {{-- The gesture is bound here rather than on the card. Alpine
                     binds `@pointerdown` and registers `x-ref` when it initialises
                     an element, and Livewire's morph patches attributes onto
                     elements that are already initialised -- so a card promoted to
                     the top by a re-render never gets either, and every swipe after
                     the first silently does nothing. The stack is the same element
                     all the way through, so binding here always works and the
                     script asks the DOM which card is on top.

                     Only the *start* of the gesture is bound to the stack. The rest
                     is bound to the window, because the end of a drag is not
                     guaranteed to be delivered here: `setPointerCapture()` is what
                     redirects a pointer that has long since left the card back to
                     this element, and the browser drops that capture on its own --
                     Safari especially, and any browser the moment the capturing
                     element is replaced by a morph. When it goes, the `pointerup`
                     lands on whatever is under the cursor instead, which by then is
                     the page behind the card. `release()` never runs, `dragging`
                     stays true, and the card sits wherever the last `pointermove`
                     painted it: tilted, stamped, and impossible to drop. The
                     keyboard still answers it, which is exactly how the bug reads
                     from the outside.

                     The window hears every pointer on the page, so each handler
                     checks the id against the one that started the drag. --}}
                    <div
                        class="cupida-stack wide:min-h-[560px] relative aspect-[3/4] max-h-[560px] w-auto max-w-full min-h-0 flex-1"
                        @pointerdown="grab($event)"
                        @pointermove.window="drag($event)"
                        @pointerup.window="release($event)"
                        @pointercancel.window="cancel($event)"
                    >
                        @foreach (array_reverse($stack) as $index => $card)
                            @php($depth = count($stack) - 1 - $index)

                            <article
                                wire:key="{{ $card->answer() }}"
                                data-depth="{{ $depth }}"
                                @class([
                                '@container cupida-card overflow-hidden p-[clamp(22px,6vw,34px)] absolute inset-0 flex flex-col justify-between',
                                'cupida-card--top' => $depth === 0,
                                                        ])
                                style="--card: {{ $card->palette->background }}; --on-card: {{ $card->palette->foreground }}; --depth: {{ $depth }}"
                            >
                                {{-- At every width. It was `hidden wide:block` for a
                                 while, on the grounds that the band above the deck
                                 has just asked the round's question -- "¿Y con
                                 quién?" over a card that says AUTORÍA -- and the
                                 label was a line of small caps charging the portrait
                                 a line of height for something already read. What
                                 that missed is that the card is `justify-between`
                                 and this is the child holding its top: a theme card
                                 carries no face and no icon, so with the label gone
                                 it had one child, and a lone child in a
                                 `justify-between` column sits at the start. The
                                 title climbed to the head of the card and left the
                                 whole lower half empty. The label is cheaper than
                                 that room.

                                 Rendered for every kind, not only the ones with a
                                 face: it is the same card in the same round, and a
                                 heading that comes and goes as the stack advances
                                 reads as a bug. --}}
                                <p class="m-0 shrink-0 text-[13px] font-bold tracking-[0.22em] uppercase opacity-70">
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
                                        draggable="false"
                                        class="mx-auto min-h-0 w-[58%] rounded-[3px] object-cover shadow-[0_8px_0_-5px_rgba(33,21,17,0.16),0_18px_36px_-18px_rgba(33,21,17,0.55)]"
                                        @style(['background: ' . $card->portrait->color => filled($card->portrait->color)])
                                    />

                                {{-- A mood card has no face and no note, so without
                                 this it is a heading on a flat color with the room a
                                 portrait takes standing empty. The icon sits in that
                                 slot, sized to the card the way the portrait is, and
                                 drawn thin and faded so it keeps company with the
                                 display type rather than shouting over it.

                                 `@svg` and not a static component tag: the name comes
                                 from the config, one per mood. `pointer-events-none`
                                 so an SVG can never swallow the drag -- the gesture
                                 belongs to the stack. --}}
                                @elseif ($card->icon)
                                    @svg("heroicon-o-{$card->icon}", 'pointer-events-none mx-auto min-h-0 w-[42%] max-w-[180px] shrink [stroke-width:1] opacity-80', ['aria-hidden' => 'true'])
                                @endif

                                {{-- The card is the only thing that can say how big
                                 its own type should be, which is what the `cqw` term
                                 is for. The deck is sized from the height left over
                                 between the question and the buttons and takes its
                                 width from that, so on a short window -- a phone with
                                 a browser's own bars top and bottom -- the card is
                                 narrow while `8vw` is exactly as big as ever, and
                                 "Voces de Latinoamérica" was written off the side of
                                 it. 100cqw is the card's content box -- its padding
                                 is already out of it -- over the widest word the
                                 catalog is likely to hand us: near 6.8em in Gloock,
                                 which is "Latinoamérica".

                                 `min()`, not a replacement: where the card has the
                                 room the viewport term still wins and the type is the
                                 size it has always been. The cq term only bites when
                                 the card is squeezed.

                                 `hyphens-auto` and `break-words` stay as the floor
                                 under all of it, for the word longer than that.
                                 Hyphenation is the half that looks right -- the page
                                 carries `lang="es"`, so "contem-poráneas" breaks where
                                 Spanish breaks -- and it does nothing at all for a name,
                                 which is most of what round two deals: no dictionary
                                 splits "Sigurdardóttir". `break-words` is what catches
                                 those, and only after hyphenation has had its turn. --}}
                                <div class="min-w-0">
                                    <h2 class="font-display text-[min(clamp(30px,8vw,46px),calc(100cqw/6.8))]/[1.02] m-0 font-normal text-balance hyphens-auto break-words">
                                        {{ $card->label }}
                                    </h2>

                                    @if ($card->note)
                                        <p class="mt-3 text-[17px]/[1.35] mb-0 italic opacity-75 hyphens-auto break-words">{{ $card->note }}</p>
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
                <div class="mt-[clamp(22px,4vw,34px)] flex shrink-0 items-center justify-center gap-[clamp(20px,6vw,40px)]">
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
                    <p class="wide:mt-3 mx-auto mt-2 mb-0 max-w-[34em] text-center text-[10px]/[1.4] tracking-[0.04em] text-balance opacity-45">
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
