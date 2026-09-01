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
        this.held = matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.touch = matchMedia('(hover: none)').matches;

        addEventListener('resize', () => {
            clearTimeout(this.resizeTimer);
            this.resizeTimer = setTimeout(() => this.build(), 240);
        });

        /* A tab hidden mid-fall stops getting frames while the settle timer
           keeps counting in wall-clock, which would freeze the books in the
           air. Coming back to the tab restarts both. */
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

        /* Nothing is dropped onto a shelf nobody is looking at: a background
           tab gets no animation frames, so the books would hang where they
           were released and the settle timer would freeze them there. */
        await whenVisible();
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
        const sizes = this.books.map((book) => ({
            ...book,
            w: (book.facesOut ? book.mm.w : book.mm.d) * this.pxPerMm,
            h: book.mm.h * this.pxPerMm,
        }));

        const used = sizes.reduce((total, book) => total + book.w + gap, 0) - gap;

        this.items = sizes;
        this.stageW = Math.max(this.scroll.clientWidth, Math.ceil(used + 52));
        this.stageH = this.stage.clientHeight;
        this.stage.style.width = `${this.stageW}px`;
        this.stage.classList.add('is-live');

        const floorY = this.stageH - BOARD_PX;
        this.floorY = floorY;
        let x = (this.stageW - used) / 2;

        this.items.forEach((item) => {
            item.x = x + item.w / 2;
            x += item.w + gap;
            item.el.style.setProperty('--tx', `${(item.x - item.w / 2).toFixed(1)}px`);
            item.el.style.setProperty('--ty', `${(floorY - item.h).toFixed(1)}px`);
        });

        try {
            this.simulate(floorY);
        } catch {
            /* A shelf that cannot be pushed is still a shelf. */
        }
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

        this.items.forEach((item, index) => {
            const lean = (Math.random() - 0.5) * (item.facesOut ? 0.18 : 0.05);

            item.body = Bodies.rectangle(
                item.x,
                this.held ? floorY - item.h / 2 : -item.h - index * 10,
                item.w,
                item.h,
                {
                    friction: 0.58,
                    frictionStatic: 1.4,
                    frictionAir: 0.012,
                    /* A book that bounces reads as a toy. */
                    restitution: 0.015,
                    density: 0.0016,
                    slop: 0.015,
                    angle: this.held ? 0 : lean,
                    chamfer: { radius: Math.min(2, item.w * 0.08) },
                    collisionFilter: { category: CATEGORY.book, mask: CATEGORY.book | CATEGORY.world },
                },
            );

            item.body.plugin = { item };
        });

        if (this.held) {
            Composite.add(this.engine.world, this.items.map((item) => item.body));
        } else {
            this.items.forEach((item, index) => {
                item.timer = setTimeout(() => Composite.add(this.engine.world, item.body), 80 + index * 60);
            });
        }

        this.mountDragging();

        Events.on(this.engine, 'afterUpdate', () => this.sync());

        this.runner = Runner.create();
        Runner.run(this.runner, this.engine);
        this.running = true;
        this.tidyPasses = 0;
        this.settleTimer = setTimeout(() => this.settle(), 3400 + this.items.length * 60);
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

            const shelved = this.floorY - item.h / 2;

            /* Height above the board is the only workable test: a book leaning
               on its neighbours sits a little low rather than high, so only one
               that is genuinely above the row is touched. */
            if (body.position.y > shelved - STRAY_PX) {
                continue;
            }

            Sleeping.set(body, false);
            Body.setPosition(body, { x: item.x, y: shelved - DROP_BACK_PX });
            Body.setAngle(body, 0);
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
        this.items?.forEach((item) => clearTimeout(item.timer));
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
function whenLoaded(img) {
    if (img.complete) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        img.addEventListener('load', resolve, { once: true });
        img.addEventListener('error', resolve, { once: true });
    });
}

function whenVisible() {
    if (document.visibilityState === 'visible') {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        document.addEventListener('visibilitychange', function onChange() {
            if (document.visibilityState === 'visible') {
                document.removeEventListener('visibilitychange', onChange);
                resolve();
            }
        });
    });
}

function number(el, property) {
    return Number.parseFloat(el.style.getPropertyValue(property)) || 0;
}
