/**
 * The shelf at /estanteria.
 *
 * Blade has already drawn the row: every book is a link with its three
 * measurements on it as custom properties. Nothing here creates a book. What
 * this does is decide the scale the millimetres are drawn at, and -- when
 * Matter.js is available -- hand the row to a physics loop so a book can be
 * pulled out of it and dropped back.
 *
 * The loop writes transforms and nothing else. If it never starts, the row is
 * still a shelf: that is what the flex layout in shelf.css is for.
 */

const SETTLE_MS = 2600;

/** How long the shelf waits on its covers before laying itself out anyway. */
const COVER_WAIT_MS = 2000;

/** How far above the board a book has to be before it counts as astray. */
const STRAY_PX = 24;

/** And how far above its slot it is lifted to be dropped back into the row. */
const DROP_BACK_PX = 40;

/** A book that will not settle in this many passes is left where it lies. */
const MAX_TIDY_PASSES = 2;

/** How long the solver is given to push the row apart once it is set down. */
const WARM_MS = 600;

/** How far apart the books arrive, so the row fills from the left. */
const ARRIVE_STEP_MS = 45;

/** How long one book takes to arrive. Mirrors --shelf-arrive in shelf.css. */
const ARRIVE_MS = 500;

/** Past this much of a lean a book has fallen over rather than settled. */
const MAX_TILT = 0.35;
const BOARD_PX = 14;

/** A dragged book stops colliding with its neighbours, so it comes out clean. */
const CATEGORY = { book: 0x0001, world: 0x0002, ceiling: 0x0004 };

export default function mountShelf(root) {
    const scroll = root.querySelector('[data-shelf-scroll]');
    const stage = root.querySelector('[data-shelf-stage]');
    const peek = root.querySelector('[data-shelf-peek]');

    if (!scroll || !stage) {
        return;
    }

    const books = [...stage.querySelectorAll('.shelf__book')].map((el) => ({
        el,
        mm: {
            w: number(el, '--mm-w'),
            h: number(el, '--mm-h'),
            d: number(el, '--mm-d'),
        },
        facesOut: el.dataset.face === '1',
        isMeasured: el.dataset.measured === '1',
        /* Books sharing a pile share one place on the board, bottom one first. */
        stack: el.dataset.stack === undefined ? null : el.dataset.stack,
    }));

    if (books.length === 0) {
        return;
    }

    const shelf = new Shelf(root, scroll, stage, books);

    mountPeek(scroll, stage, peek, () => shelf.wasDrag);

    /* A click that came out of a drag is not a click. Everything else is left
       to the anchor, so middle-click, cmd-click and Enter still work. */
    stage.addEventListener('click', (event) => {
        if (event.target.closest('.shelf__book') && shelf.wasDrag) {
            event.preventDefault();
        }
    });

    shelf.start();
}

/**
 * The row, and the physics loop over it.
 */
class Shelf {
    constructor(root, scroll, stage, books) {
        this.root = root;
        this.scroll = scroll;
        this.stage = stage;
        this.books = books;
        this.running = false;
        this.wasDrag = false;
        this.touch = matchMedia('(hover: none)').matches;

        addEventListener('resize', () => {
            clearTimeout(this.resizeTimer);
            this.resizeTimer = setTimeout(() => this.build(), 240);
        });

        /* A tab hidden while the reader is rummaging stops getting frames while
           the settle timer keeps counting in wall-clock. Coming back to the tab
           restarts both, so a book left mid-air is put down. */
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState !== 'visible' || !this.engine) {
                return;
            }

            this.wake();
            clearTimeout(this.settleTimer);
            this.tidyPasses = 0;
            this.settleTimer = setTimeout(() => this.settle(), SETTLE_MS);
        });
    }

    async start() {
        this.scale();

        /* Matter is fetched only here, so the 90KB never reaches a reader who
           lands on any other page -- and a failure to fetch it leaves a shelf
           that is merely still rather than a page that is broken. */
        try {
            this.Matter = (await import('matter-js')).default;
        } catch {
            return;
        }

        await this.fitCoversToFaces();

        this.build();
    }

    /**
     * Take the shape of every unmeasured book off its cover.
     *
     * A book nobody has measured stands at the ordinary size for its binding,
     * and that is a guess about its proportions as well as its size. The cover
     * is not a guess: however tall the book really is, the picture on the front
     * has the shape of the front. So the width is read back off the image and
     * only the height stays estimated -- otherwise the cover is cropped to fit
     * a shape the book does not have, which is what a board-book guess does to
     * an ordinary paperback.
     *
     * This is why the covers are not lazy. The shelf is one row of at most a
     * couple of dozen thumbnails and the physics needs their proportions before
     * it can lay the row out, so they are waited for here -- but only so long:
     * an image that never arrives leaves its book at the estimate rather than
     * holding up the shelf.
     */
    async fitCoversToFaces() {
        const fitting = this.books.map(async (book) => {
            const cover = book.isMeasured ? null : book.el.querySelector('img');

            if (!cover) {
                return;
            }

            await whenLoaded(cover);

            if (!cover.naturalWidth || !cover.naturalHeight) {
                return;
            }

            book.mm.w = Math.round(book.mm.h * (cover.naturalWidth / cover.naturalHeight));
            book.el.style.setProperty('--mm-w', String(book.mm.w));
        });

        await Promise.race([
            Promise.all(fitting),
            new Promise((resolve) => setTimeout(resolve, COVER_WAIT_MS)),
        ]);
    }

    /**
     * How many pixels a millimetre is worth.
     *
     * The height of the stage decides it, never the width: if the row does not
     * fit it overflows and is scrolled, because shrinking the books to fit
     * would throw away the one thing this page is for.
     */
    scale() {
        const tallest = Math.max(...this.books.map((book) => book.mm.h));
        const available = Math.max(180, this.stage.clientHeight - 16);

        this.pxPerMm = Math.min(1.9, Math.max(0.7, (available * 0.96) / tallest));

        /* Written onto the stage rather than onto .shelf, which declares the
           default: a value set on an ancestor would lose to that declaration. */
        this.stage.style.setProperty('--shelf-scale', this.pxPerMm.toFixed(3));
    }

    build() {
        if (!this.Matter) {
            return;
        }

        this.destroy();
        this.scale();

        const gap = 3;

        /* A book lying flat is a spine-out book rolled a quarter turn: what it
           takes up along the board is its height, and what it stands is its
           thickness. The element's own box never changes -- the rotation is the
           body's angle, which sync() writes out as --rot -- so w and h stay the
           unrotated box that --tx and --ty are measured against. */
        this.items = this.books.map((book) => {
            const flat = book.stack !== null;
            const w = (book.facesOut ? book.mm.w : book.mm.d) * this.pxPerMm;
            const h = book.mm.h * this.pxPerMm;

            return {
                ...book, w, h, flat,
                footprint: flat ? h : w,
                rise: flat ? w : h,
                /* Anticlockwise, not clockwise. The spine is set in
                   writing-mode: vertical-rl, which has already turned the
                   glyphs a quarter turn clockwise to read top to bottom; a
                   second clockwise quarter turn leaves the title upside down.
                   This one puts it back upright, reading left to right, which
                   is how the spine of a book lying flat is read. */
                angle: flat ? -Math.PI / 2 : 0,
            };
        });

        const slots = this.intoSlots();
        const width = (slot) => Math.max(...slot.map((item) => item.footprint));
        const used = slots.reduce((total, slot) => total + width(slot) + gap, 0) - gap;

        this.stageW = Math.max(this.scroll.clientWidth, Math.ceil(used + 52));
        this.stageH = this.stage.clientHeight;
        this.stage.style.width = `${this.stageW}px`;
        this.stage.classList.add('is-live');

        const floorY = this.stageH - BOARD_PX;
        let x = (this.stageW - used) / 2;

        this.arrive(slots);

        for (const slot of slots) {
            const centre = x + width(slot) / 2;
            let stacked = 0;

            /* Down the pile from the board up, each book on the one below. */
            for (const item of slot) {
                item.x = centre;
                item.restY = floorY - stacked - item.rise / 2;
                stacked += item.rise;

                item.el.style.setProperty('--tx', `${(item.x - item.w / 2).toFixed(1)}px`);
                item.el.style.setProperty('--ty', `${(item.restY - item.h / 2).toFixed(1)}px`);
                item.el.style.setProperty('--rot', `${item.angle.toFixed(4)}rad`);
            }

            x += width(slot) + gap;
        }

        try {
            this.simulate(floorY);
        } catch {
            /* A shelf that cannot be pushed is still a shelf. */
        }
    }

    /**
     * Let the row fill up left to right, once and only once.
     *
     * A place on the board arrives at a time, so the three books of a pile come
     * in together rather than one after another. The delays are set here
     * because only the layout knows the order books stand in, and a rebuild --
     * a resize -- neither replays the entrance nor re-hides anything.
     *
     * The class is taken off again at the end. That is not tidiness: an
     * animation does not advance in a tab nobody is looking at, so its filled
     * `from` state would leave the whole shelf at zero opacity until someone
     * looked. A timer still runs there, throttled but running, so this is what
     * guarantees the books are visible either way.
     *
     * @param {Array<Array<object>>} slots
     */
    arrive(slots) {
        if (this.arriving) {
            return;
        }

        this.arriving = true;
        let place = 0;

        for (const slot of slots) {
            for (const item of slot) {
                item.el.style.setProperty('--arrive-delay', `${place * ARRIVE_STEP_MS}ms`);
            }

            place++;
        }

        this.stage.classList.add('is-arriving');

        const lastArrival = (place - 1) * ARRIVE_STEP_MS + ARRIVE_MS;

        setTimeout(() => this.stage.classList.remove('is-arriving'), lastArrival + 400);
    }

    /**
     * The places along the board: one standing book each, or one pile.
     *
     * The pile is re-sorted here rather than trusted as it arrives. The server
     * orders it by footprint too, but it can only do so from the sizes it has,
     * and an unmeasured book is estimated at the ordinary size for its binding
     * -- so a shelf of paperbacks ties on every book and the order comes out
     * arbitrary. By this point `fitCoversToFaces` has taken each book's real
     * proportions off its cover, so the biggest one can actually be put at the
     * bottom, which is both what holds a pile up and what anyone stacking
     * books does.
     *
     * @return {Array<Array<object>>}
     */
    intoSlots() {
        const slots = [];

        for (const item of this.items) {
            const open = slots.at(-1);

            if (item.flat && open?.at(-1)?.flat && open.at(-1).stack === item.stack) {
                open.push(item);
            } else {
                slots.push([item]);
            }
        }

        for (const slot of slots) {
            if (slot.length > 1) {
                slot.sort((a, b) => footprintOf(b) - footprintOf(a));
            }
        }

        return slots;
    }

    simulate(floorY) {
        const { Engine, Runner, Bodies, Composite, Events } = this.Matter;

        this.engine = Engine.create({ enableSleeping: true });
        this.engine.gravity.y = 1;
        this.engine.positionIterations = 8;
        this.engine.velocityIterations = 6;

        const wall = {
            isStatic: true,
            collisionFilter: { category: CATEGORY.world, mask: CATEGORY.book },
        };

        Composite.add(this.engine.world, [
            Bodies.rectangle(this.stageW / 2, floorY + 60, this.stageW + 400, 120, { ...wall, friction: 0.9 }),
            /* The ceiling stops a book being dragged off the top. The others
               fall through it, which is how they get onto the shelf at all. */
            Bodies.rectangle(this.stageW / 2, -20, this.stageW + 400, 40, {
                isStatic: true,
                collisionFilter: { category: CATEGORY.ceiling, mask: CATEGORY.book },
            }),
            Bodies.rectangle(-40, this.stageH / 2, 80, this.stageH * 4, wall),
            Bodies.rectangle(this.stageW + 40, this.stageH / 2, 80, this.stageH * 4, wall),
        ]);

        /* Every book is set down where it belongs. Nothing is dropped: books
           rained onto a board land on each other's corners, and a spine 25px
           wide and 400 tall topples from the lightest knock -- over half of
           shelves built that way ended up with a book lying flat, most often
           the first or the last to arrive, which has a neighbour on one side
           only. Bookends do not save it either; falling books simply land on
           those instead. So the shelf arrives already standing, and the
           physics is there for what the reader does to it. */
        for (const item of this.items) {
            item.body = Bodies.rectangle(item.x, item.restY, item.w, item.h, {
                friction: 0.58,
                frictionStatic: 1.4,
                frictionAir: 0.012,
                /* A book that bounces reads as a toy. */
                restitution: 0.015,
                density: 0.0016,
                slop: 0.015,
                angle: item.angle,
                chamfer: { radius: Math.min(2, item.w * 0.08) },
                collisionFilter: { category: CATEGORY.book, mask: CATEGORY.book | CATEGORY.world },
            });

            item.body.plugin = { item };
        }

        Composite.add(this.engine.world, this.items.map((item) => item.body));

        this.mountDragging();

        Events.on(this.engine, 'afterUpdate', () => this.sync());

        this.runner = Runner.create();
        Runner.run(this.runner, this.engine);
        this.running = true;
        this.tidyPasses = 0;

        /* Long enough to let the solver push out the last micron of overlap
           between books that were placed touching, and no longer. */
        this.settleTimer = setTimeout(() => this.settle(), WARM_MS);
    }

    mountDragging() {
        /* No dragging on a touch screen: it would fight the scroll, and the
           shelf is scrolled far more often than it is rummaged in. */
        if (this.touch) {
            return;
        }

        const { Mouse, MouseConstraint, Composite, Events } = this.Matter;
        const mouse = Mouse.create(this.scroll);

        /* Matter registers wheel and touch handlers that preventDefault. Left
           alone, they take the page's scrolling away. */
        mouse.element.removeEventListener('wheel', mouse.mousewheel);
        mouse.element.removeEventListener('touchstart', mouse.mousedown);
        mouse.element.removeEventListener('touchmove', mouse.mousemove);
        mouse.element.removeEventListener('touchend', mouse.mouseup);

        this.drag = MouseConstraint.create(this.engine, {
            mouse,
            constraint: { stiffness: 0.32, damping: 0.15, render: { visible: false } },
        });

        Composite.add(this.engine.world, this.drag);

        this.scroll.addEventListener('pointerdown', () => {
            this.wasDrag = false;
            this.wake();
        });

        let from = null;

        Events.on(this.drag, 'startdrag', (event) => {
            from = { at: performance.now(), x: mouse.position.x, y: mouse.position.y };
            event.body?.plugin?.item?.el.classList.add('is-dragging');

            if (event.body) {
                event.body.collisionFilter.mask = CATEGORY.world | CATEGORY.ceiling;
            }

            this.wake();
        });

        Events.on(this.drag, 'enddrag', (event) => {
            event.body?.plugin?.item?.el.classList.remove('is-dragging');

            /* Put it back in the row only once it has fallen clear. Restored on
               top of a neighbour, the solver fires it across the page. */
            if (event.body) {
                setTimeout(() => {
                    event.body.collisionFilter.mask = CATEGORY.book | CATEGORY.world;
                }, 380);
            }

            const travelled = Math.hypot(mouse.position.x - from.x, mouse.position.y - from.y);

            /* Under 6px and under 320ms is a click, not a drag. */
            this.wasDrag = travelled > 6 || performance.now() - from.at > 320;

            clearTimeout(this.settleTimer);
            this.tidyPasses = 0;
            this.settleTimer = setTimeout(() => this.settle(), SETTLE_MS);
        });
    }

    /**
     * Stop the loop, unless a book is not where a book should be.
     *
     * Dragging is the one move that can leave one somewhere else. A book being
     * pulled stops colliding with its neighbours so that it comes out of the
     * row cleanly rather than shouldering its way out, and for a moment after
     * it is let go it is still passing through them. Released over the row, it
     * can have that collision restored while it overlaps two other books, and
     * the solver resolves that by standing it on top of them -- which is quite
     * stable, and reads as a book hanging in the air.
     *
     * So anything not standing on the board is lifted back over its own slot
     * and dropped the last few centimetres into it. Twice at most: a book that
     * will not settle is left alone rather than dropped forever.
     */
    settle() {
        if (this.tidyPasses < MAX_TIDY_PASSES && this.tidyStrays()) {
            this.tidyPasses++;
            this.wake();
            this.settleTimer = setTimeout(() => this.settle(), SETTLE_MS);

            return;
        }

        this.freeze();
    }

    /**
     * @return {boolean} whether anything had to be put back
     */
    tidyStrays() {
        const { Body, Sleeping } = this.Matter;
        let strays = false;

        for (const item of this.items) {
            const body = item.body;

            if (!body) {
                continue;
            }

            /* Two ways to be out of place, and a book only needs one.

               Too high: measured against its own place rather than the board,
               because a book part way up a pile belongs above the board by
               exactly the thickness of what is under it.

               Too far over: a book knocked flat comes to rest *below* where it
               should be, not above, so height alone never catches it. A lean is
               ordinary; a quarter turn is a book lying down. */
            const upright = turnedFrom(body.angle, item.angle) <= MAX_TILT;

            if (upright && body.position.y > item.restY - STRAY_PX) {
                continue;
            }

            Sleeping.set(body, false);
            Body.setPosition(body, { x: item.x, y: item.restY - DROP_BACK_PX });
            Body.setAngle(body, item.angle);
            Body.setVelocity(body, { x: 0, y: 0 });
            Body.setAngularVelocity(body, 0);
            item.parked = false;
            strays = true;
        }

        return strays;
    }

    /** The only place the loop touches the DOM. */
    sync() {
        for (const item of this.items) {
            const body = item.body;

            if (!body || (body.isSleeping && item.parked)) {
                continue;
            }

            item.el.style.setProperty('--tx', `${(body.position.x - item.w / 2).toFixed(2)}px`);
            item.el.style.setProperty('--ty', `${(body.position.y - item.h / 2).toFixed(2)}px`);
            item.el.style.setProperty('--rot', `${body.angle.toFixed(4)}rad`);
            item.parked = body.isSleeping;
        }
    }

    /**
     * Stopping the runner costs no CPU at all. The bodies stay dynamic on
     * purpose: made static, they stop firing 'startdrag' and there is then no
     * way left to wake the world up.
     */
    wake() {
        if (this.running || !this.engine) {
            return;
        }

        this.Matter.Runner.run(this.runner, this.engine);
        this.running = true;
    }

    freeze() {
        if (!this.running || !this.engine) {
            return;
        }

        this.sync();
        this.Matter.Runner.stop(this.runner);
        this.running = false;
    }

    destroy() {
        clearTimeout(this.settleTimer);

        if (this.runner) {
            this.Matter.Runner.stop(this.runner);
        }

        if (this.engine) {
            this.Matter.Events.off(this.engine);
            this.Matter.Composite.clear(this.engine.world, false);
            this.Matter.Engine.clear(this.engine);
        }

        this.engine = null;
        this.running = false;
    }
}

/**
 * The card that follows the pointer along the row: title, author, price.
 */
function mountPeek(scroll, stage, peek, suppressed) {
    if (!peek) {
        return;
    }

    const title = peek.querySelector('[data-shelf-peek-title]');
    const author = peek.querySelector('[data-shelf-peek-author]');
    const note = peek.querySelector('[data-shelf-peek-note]');

    const show = (book) => {
        if (!book || suppressed()) {
            return;
        }

        const box = book.getBoundingClientRect();
        const frame = scroll.getBoundingClientRect();

        title.textContent = book.dataset.title ?? '';
        author.textContent = book.dataset.author ?? '';
        note.textContent = book.dataset.note ?? '';

        peek.style.left = `${box.left - frame.left + scroll.scrollLeft + box.width / 2}px`;
        peek.style.top = `${box.top - frame.top}px`;
        peek.classList.add('is-on');
    };

    const hide = () => peek.classList.remove('is-on');

    stage.addEventListener('pointerover', (event) => show(event.target.closest('.shelf__book')));
    stage.addEventListener('pointerout', (event) => {
        if (!event.relatedTarget?.closest?.('.shelf__book')) {
            hide();
        }
    });
    stage.addEventListener('focusin', (event) => show(event.target.closest('.shelf__book')));
    stage.addEventListener('focusout', hide);
}

/**
 * Resolves once the image has finished loading, one way or the other.
 *
 * Deliberately not decode(): the natural size is known as soon as the header
 * is parsed, whereas decoding is rendering work a browser is free to put off
 * indefinitely in a tab nobody is looking at.
 */
/**
 * How far one angle is from another, the short way round, in radians.
 *
 * A body's angle accumulates: a book spun twice reads as 4pi rather than 0, so
 * the difference has to be wrapped before it can be compared to a tolerance.
 */
/**
 * How much board a book covers lying on its back, in square millimetres.
 */
function footprintOf(item) {
    return item.mm.w * item.mm.h;
}

function turnedFrom(angle, from) {
    const difference = angle - from;

    return Math.abs(Math.atan2(Math.sin(difference), Math.cos(difference)));
}

function whenLoaded(img) {
    if (img.complete) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        img.addEventListener('load', resolve, { once: true });
        img.addEventListener('error', resolve, { once: true });
    });
}

function number(el, property) {
    return Number.parseFloat(el.style.getPropertyValue(property)) || 0;
}
