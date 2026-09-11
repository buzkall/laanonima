<x-layouts.shelf
    :title="__('cupida.title')"
    :description="__('cupida.intro')"
    :palette="$palette"
    :footer-cta="false"
    {{-- Every panel of this page is measured to fill a phone screen exactly, so
         the one line of footer under it is a strip of cream the deck would
         rather have. It stays from `wide:` up. --}}
    :footer-on-phone="false"
    fits-viewport
    :og-image="Vite::asset('resources/images/brand/la-cupida-og.jpg')"
>
    <livewire:cupida :guest="$guest" />

    {{--
    The swipe gesture.

    Inline, and in the page rather than in the component, for one reason that is
    easy to rediscover the hard way. `resources/js/app.js` is a `type="module"`
    tag in the head, so it is deferred, and Livewire injects its own bundle as a
    plain script before `</body>`. A classic script in the body runs during
    parsing and a deferred module runs after it, so Livewire boots Alpine -- and
    `alpine:init` fires -- before a line of app.js has executed. A component
    registered from there is always too late and `x-data` fails with
    "cupidaDeck is not defined". Here the parser reaches this first, which is
    the whole guarantee.

    In the page's view rather than the component's so that a Livewire
    re-render, which morphs the component and leaves scripts alone, can never
    re-run it.

    The two buttons under the stack call the same `answer()` this does, so
    nothing here is the only way to answer a card: the drag is laid over the top
    of a control that already works without it.
--}}
    <script>
        document.addEventListener('alpine:init', () => {
            /** How far across the card has to go before letting go answers it. */
            const DISTANCE = 0.25;

            /** ...or how fast it has to be moving, for a flick that never gets that far. */
            const VELOCITY = 0.5;

            /** Degrees of tilt at the edge of the card, which is all the rotation there is. */
            const TILT = 14;

            /**
             * How far the opening hint pulls the card, as a fraction of its width.
             *
             * Under DISTANCE, always: the hint shows the start of the gesture,
             * not a completed one. At this reach the stamp comes up to about
             * half strength, which is enough to read during the hold.
             */
            const REACH = 0.22;

            /** How long the hint takes to carry the card out to one side... */
            const GLIDE = 700;

            /** ...and to bring it back to the centre. */
            const RETURN = 500;

            /**
             * Where the browser remembers that it has shown the hint.
             *
             * The server's `coached` flag lasts one page load; this is what
             * makes "once" mean once per browser. Both are kept: a browser
             * with storage switched off falls back to the server's word.
             */
            const SEEN_KEY = 'cupida:coached';

            Alpine.data('cupidaDeck', (options = {}) => ({
                dragging: false,

                /**
                 * The pointer the drag belongs to, or null between drags.
                 *
                 * The gesture ends on the window rather than on the stack, so
                 * every pointer on the page is heard and each one has to be
                 * matched against this before it is allowed to move or drop the
                 * card. `null` rather than `0`: a pointer id of zero is a real id
                 * in some browsers, and a resting deck must not answer to it.
                 */
                pointer: null,
                start: 0,
                startedAt: 0,
                dx: 0,

                /**
                 * The card already answered and waiting to be taken away.
                 *
                 * Keyed on the card rather than kept as a boolean something has to
                 * remember to unset: the re-render brings a different card to the
                 * top, its key does not match, and the deck is live again on its
                 * own. A flag cleared in a promise callback is a flag that stays
                 * set forever the one time that callback does not run.
                 */
                spentKey: null,

                /**
                 * A swipe is in the air.
                 *
                 * `spentKey` alone is not enough: it only guards the card that was
                 * just answered, and the moment the re-render promotes the next one
                 * that guard is satisfied again -- so a fast hand can answer a
                 * second card while the first is still flying, and the deck ends up
                 * with two cards half off the screen and a server that disagrees
                 * about which round it is on. One at a time.
                 */
                busy: false,

                /**
                 * The timers the opening hint is still waiting on.
                 *
                 * Kept so the first touch can call them off. A step that fires
                 * after a finger is down paints over the drag, and one that
                 * fires after a button was pressed drags a card back that is
                 * already on its way off the screen.
                 */
                hints: [],

                /**
                 * The hint is on screen, in either of its two forms -- the
                 * automatic one on the first card, or the one the info button
                 * asks for. The strip of words over the deck shows while this
                 * is true.
                 */
                coaching: false,

                /**
                 * Show the reader, once, that the card moves.
                 *
                 * The deck has three inputs and only two of them announce
                 * themselves: the buttons are on the screen and the arrow keys
                 * are named in a line that is hidden below `wide:`. On a phone,
                 * which is the width this page was drawn for, nothing says the
                 * card can be thrown -- so the first card throws itself a
                 * little, each way, and stops.
                 *
                 * `options.coach` comes from the server (`Cupida::$coached`),
                 * so a reader who has answered a card is never shown it again,
                 * including after "Otra vez". `seen()` is the browser's own
                 * memory of it, across page loads.
                 */
                init() {
                    if (! options.coach || this.seen() || this.reduced()) {
                        return;
                    }

                    /* Not into a tab nobody is looking at. A hidden tab has its
                       timers clamped into one-second buckets, so the four steps
                       arrive in a lump rather than as a sequence -- and the
                       reader comes back to a card either mid-twitch or already
                       finished. A hint that is not watched is not worth
                       spending; better no hint than that one. */
                    if (document.hidden) {
                        return;
                    }

                    /* Remembered when it starts, not when it ends: a reader who
                       grabs the card halfway through has plainly got it. */
                    this.remember();
                    this.coach();
                },

                /**
                 * The card on top right now, asked of the DOM every time.
                 *
                 * Never held in a variable and never an `x-ref`: Livewire replaces
                 * the stack on every swipe, so anything remembered from before a
                 * re-render points at a card that has already gone.
                 *
                 * `$root`, never `$el`. Alpine resolves `$el` to whichever element
                 * the expression being evaluated is bound to, so the same method
                 * sees the stack when the gesture calls it and the button when a
                 * button does -- and searching inside a button for a card finds
                 * nothing, silently, which looks exactly like a swipe that did not
                 * register. `$root` is the component's own element either way.
                 */
                top() {
                    return this.$root.querySelector('.cupida-card--top');
                },

                grab(event) {
                    /* Before the guards, and before `paint()`: a finger on the
                       card ends the hint whether or not this particular pointer
                       is allowed to drag it. */
                    this.stopCoaching();

                    const card = this.top();

                    /* Same test as answer(): the card on top is fair game unless it
                   is the one already sent. `spentKey` is never reset, so asking
                   whether it is set would jam the deck after the first swipe. */
                    if (event.button > 0 || this.busy || !card || this.spentKey === card.getAttribute('wire:key')) {
                        return;
                    }

                    this.dragging = true;
                    this.pointer = event.pointerId;
                    this.start = event.clientX;

                    /* `performance.now()`, not `event.timeStamp`, because the other
                   half of this subtraction is `performance.now()`. The two agree
                   in every browser that matters, and on the day one of them does
                   not the mistake is not a slightly wrong velocity -- it is a
                   negative elapsed, clamped to a millisecond, and a card that
                   flies off the screen on the gentlest nudge. */
                    this.startedAt = performance.now();
                    this.dx = 0;

                    /* Keep receiving moves after the pointer leaves the card, which
                   it does long before the throw is over. Best effort only: the
                   browser is free to hand the capture back at any moment, and
                   `setPointerCapture` itself throws if the pointer is already
                   gone. Nothing below depends on it -- the drag is finished on
                   the window either way -- so a failure here is not a reason to
                   abandon a gesture that has already started. */
                    try {
                        event.currentTarget.setPointerCapture?.(event.pointerId);
                    } catch {
                        /* Fine. The window is listening. */
                    }

                    this.paint(0, false);
                },

                drag(event) {
                    if (!this.dragging || event.pointerId !== this.pointer) {
                        return;
                    }

                    /* The button came back up somewhere we were not told about --
                   over the browser's own chrome, in another window, at the end
                   of a native image drag. There is no `pointerup` coming for
                   this gesture, so the next move is the one chance to finish
                   it. Mouse only: `buttons` is 0 for a pen hovering and for a
                   touch that is very much still down. */
                    if (event.pointerType === 'mouse' && event.buttons === 0) {
                        this.release(event);

                        return;
                    }

                    this.dx = event.clientX - this.start;
                    this.paint(this.dx, false);
                },

                release(event) {
                    if (!this.dragging || (event && event.pointerId !== this.pointer)) {
                        return;
                    }

                    this.dragging = false;
                    this.pointer = null;

                    const card = this.top();
                    const width = card?.offsetWidth || 1;
                    const elapsed = Math.max(performance.now() - this.startedAt, 1);
                    const speed = Math.abs(this.dx) / elapsed;

                    if (Math.abs(this.dx) > width * DISTANCE || speed > VELOCITY) {
                        this.answer(this.dx > 0);

                        return;
                    }

                    /* Under the threshold: spring back, and let the transition do it. */
                    this.paint(0, true);
                },

                /**
                 * The browser took the gesture away: a scroll it decided to own, a
                 * native drag of the portrait, the page going to the background.
                 *
                 * Always springs back, never answers. A `pointercancel` is the one
                 * ending that carries no intent -- the reader did not let go of the
                 * card, something else let go of it for them -- and a round of six
                 * is short enough that a swipe silently spent on a mis-read scroll
                 * is worse than a card that comes back and asks again.
                 */
                cancel(event) {
                    if (!this.dragging || (event && event.pointerId !== this.pointer)) {
                        return;
                    }

                    this.dragging = false;
                    this.pointer = null;
                    this.dx = 0;
                    this.paint(0, true);
                },

                /** The one way a card is answered, whichever of the three inputs asked. */
                answer(liked) {
                    /* The buttons and the arrow keys can answer a card while the
                       hint is still playing, and `fly()` writes a transform that
                       a pending step would then undo mid-flight. */
                    this.stopCoaching();

                    const card = this.top();

                    if (!card) {
                        return;
                    }

                    const key = card.getAttribute('wire:key');

                    /* The gesture and the buttons can both answer one card, when a
                   pointer is released over the button the card flew past. */
                    if (this.busy || this.spentKey === key) {
                        return;
                    }

                    this.spentKey = key;
                    this.busy = true;

                    /* Whatever asked, the drag is over: the buttons and the arrow
                   keys can answer a card while a pointer is still down on it,
                   and a gesture left running would go on painting a card that
                   is already on its way off the screen. */
                    this.dragging = false;
                    this.pointer = null;
                    this.dx = 0;

                    const width = card.offsetWidth || 320;

                    /* Told at once rather than after the animation. Holding the
                   call back until the card is off screen reads better by a
                   tenth of a second and puts the one thing that must not be
                   lost -- the answer -- behind a timer. The re-render takes the
                   flying card away with it and writes the next card's style
                   attribute out fresh, so there is nothing to tidy up after. */
                    this.fly(card, liked ? width * 2 : -width * 2);

                    Promise.resolve(this.$wire.swipe(key, liked)).finally(() => {
                        this.busy = false;
                    });

                    /* A deck that never unlocks is worse than one that unlocks a
                   beat early, so the flag has a way out that does not depend on
                   a promise settling. */
                    window.setTimeout(() => {
                        this.busy = false;
                    }, 2000);
                },

                fly(card, to) {
                    card.style.transition = 'transform 220ms ease-in, opacity 220ms ease-in';
                    card.style.transform = `translateX(${to}px) rotate(${to > 0 ? TILT : -TILT}deg)`;
                    card.style.opacity = '0';
                },

                /**
                 * Write the drag onto the card: the offset, the tilt that follows
                 * from it, and how strongly each of the two stamps shows through.
                 */
                paint(dx, animated, duration = 220) {
                    const card = this.top();

                    if (!card) {
                        return;
                    }

                    const width = card.offsetWidth || 1;
                    const ratio = Math.max(-1, Math.min(1, dx / width));

                    /* The default is the drag's spring-back, and `release()` and
                       `cancel()` never say otherwise. Only the hint asks for
                       longer, because only the hint is moving the card for
                       somebody who is watching rather than doing. */
                    card.style.transition = animated ? `transform ${duration}ms cubic-bezier(0.22, 1, 0.36, 1)` : 'none';
                    card.style.transform = `translateX(${dx}px) rotate(${ratio * TILT}deg)`;
                    card.style.setProperty('--like', String(Math.max(0, ratio) * 2.5));
                    card.style.setProperty('--pass', String(Math.max(0, -ratio) * 2.5));
                },

                /**
                 * What the info button does: the same hint, on request.
                 *
                 * A second press while it is running starts it over rather
                 * than stacking a second set of timers on the first. It waits
                 * for a card that is flying to be gone -- `coach()` would
                 * paint the one underneath, which is not on top yet.
                 *
                 * With motion turned off in the OS the words still show; the
                 * setting is about movement, and the words are the part that
                 * works without it. No `document.hidden` guard: the reader
                 * just pressed the button, so the tab is in front.
                 */
                explain() {
                    this.stopCoaching();

                    if (this.busy) {
                        return;
                    }

                    if (this.reduced()) {
                        this.coaching = true;
                        this.hints.push(window.setTimeout(() => this.stopCoaching(), 6400));

                        return;
                    }

                    this.coach();
                },

                /**
                 * The opening hint: right a little, back, left a little, back.
                 *
                 * Written through `paint()` rather than as a keyframe on the
                 * card, and that is not a preference. The gesture owns the top
                 * card's `transform` as an inline style, and an animated
                 * property outranks an inline declaration -- so a CSS animation
                 * on the card would go on overriding the drag until it ended,
                 * and the card would ignore the finger. Going through `paint()`
                 * writes the same inline style the drag writes: nothing to
                 * fight, and nothing to tear down afterwards.
                 *
                 * It also means the tilt and the two stamps come for free, at
                 * the strength `REACH` earns them. That is the half worth
                 * having: the reader is not only shown that the card moves, but
                 * that moving it one way says "Me gusta" and the other "Paso".
                 *
                 * `REACH` stays under `DISTANCE`, the threshold that commits a
                 * real drag. The hint shows the beginning of the gesture, not a
                 * completed one.
                 *
                 * Once, never on a loop. `.cupida-nudge` on the opening card
                 * loops because nothing else on that panel moves and it is
                 * asking to be pressed; a card that keeps moving under somebody
                 * who is reading it is a card arguing with them.
                 *
                 * Slow, and held. The first cut of this used the drag's own
                 * 220ms glides and was over in two seconds, which read as a
                 * twitch: a reader who blinked had missed it and did not know
                 * what they had missed. Now the card takes most of a second to
                 * get out to the side and stays there a full second with the
                 * stamp up before coming back -- long enough to read the word
                 * and the words under the deck that go with it.
                 */
                coach() {
                    const card = this.top();

                    if (! card) {
                        return;
                    }

                    /* The lead-in is also what makes the measurement safe. The
                       deck takes its size from `flex-1` and an aspect ratio, so
                       asking for `offsetWidth` in the tick Alpine initialises
                       the element is asking early -- and the fallback is the
                       same one `answer()` uses. */
                    const step = (at, run) => this.hints.push(window.setTimeout(run, at));
                    const reach = () => (this.top()?.offsetWidth || 320) * REACH;

                    /* The stylesheet reads this so the stamp fades in at the
                       speed the card moves, rather than at the drag's. */
                    card.style.setProperty('--cupida-glide', `${GLIDE}ms`);
                    card.classList.add('cupida-hint');
                    this.coaching = true;

                    step(900, () => this.paint(reach(), true, GLIDE));
                    step(2600, () => this.paint(0, true, RETURN));
                    step(3400, () => this.paint(-reach(), true, GLIDE));
                    step(5100, () => this.paint(0, true, RETURN));
                    step(6400, () => this.stopCoaching());
                },

                /**
                 * Call the hint off, wherever it had got to.
                 *
                 * Safe to call at any time, including long after the hint has
                 * finished and on a deck that never ran one: an empty list of
                 * timers clears to an empty list, and the class is removed from
                 * whichever card is on top whether or not it was ever added.
                 */
                stopCoaching() {
                    this.hints.forEach((id) => window.clearTimeout(id));
                    this.hints = [];
                    this.coaching = false;
                    this.top()?.classList.remove('cupida-hint');
                },

                /**
                 * Whether this browser has already shown the hint.
                 *
                 * Both halves in a try/catch, and both fail towards "no":
                 * Safari in a private window throws on `setItem`, and a
                 * browser with storage switched off throws on the read, and
                 * an exception out of `init()` is a deck with no hint *and*
                 * no drag. The worst a failure here can do is show the hint
                 * one more time.
                 */
                seen() {
                    try {
                        return window.localStorage.getItem(SEEN_KEY) !== null;
                    } catch {
                        return false;
                    }
                },

                remember() {
                    try {
                        window.localStorage.setItem(SEEN_KEY, '1');
                    } catch {
                        /* Fine. The server's flag still holds for this page load. */
                    }
                },

                reduced() {
                    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                },
            }));
        });
    </script>
</x-layouts.shelf>
