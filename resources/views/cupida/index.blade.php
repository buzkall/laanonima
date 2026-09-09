<x-layouts.shelf
    :title="__('cupida.title')"
    :description="__('cupida.intro')"
    :palette="$palette"
    :footer-cta="false"
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

            Alpine.data('cupidaDeck', () => ({
                dragging: false,
                pointer: 0,
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
                    this.startedAt = event.timeStamp;
                    this.dx = 0;

                    /* Keep receiving moves after the pointer leaves the card, which
                   it does long before the throw is over. */
                    event.currentTarget.setPointerCapture?.(event.pointerId);
                    this.paint(0, false);
                },

                drag(event) {
                    if (!this.dragging || event.pointerId !== this.pointer) {
                        return;
                    }

                    this.dx = event.clientX - this.start;
                    this.paint(this.dx, false);
                },

                release() {
                    if (!this.dragging) {
                        return;
                    }

                    this.dragging = false;

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

                /** The one way a card is answered, whichever of the three inputs asked. */
                answer(liked) {
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
                paint(dx, animated) {
                    const card = this.top();

                    if (!card) {
                        return;
                    }

                    const width = card.offsetWidth || 1;
                    const ratio = Math.max(-1, Math.min(1, dx / width));

                    card.style.transition = animated ? 'transform 220ms cubic-bezier(0.22, 1, 0.36, 1)' : 'none';
                    card.style.transform = `translateX(${dx}px) rotate(${ratio * TILT}deg)`;
                    card.style.setProperty('--like', String(Math.max(0, ratio) * 2.5));
                    card.style.setProperty('--pass', String(Math.max(0, -ratio) * 2.5));
                },

                reduced() {
                    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                },
            }));
        });
    </script>
</x-layouts.shelf>
