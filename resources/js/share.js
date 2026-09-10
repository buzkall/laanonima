/**
 * One share control, for every page that carries one.
 *
 * The button is `[data-share]` and holds everything it shares in data
 * attributes, so nothing has to be wired up per page and a Livewire morph
 * cannot strand a listener: La Cupida repaints its whole result panel on every
 * round, and the listener here is on the document and looks the button up at
 * click time.
 *
 * `navigator.share` is what a phone wants and what most desktop browsers do not
 * have, so the fallback copies the address and says so in the button's own
 * label. Neither exists over plain http, which is what the textarea is for -- a
 * bookseller on the shop's laptop should still be able to send somebody a book.
 */
const COPIED_FOR_MS = 2400;

/* Not `if (navigator.clipboard)` but a real attempt, because the modern one is
   present and refuses in more cases than it is absent: an unfocused document
   and a denied permission both reject, and the old way is allowed in both. */
async function copy(url) {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);

            return true;
        }
    } catch {
        /* Falls through to the textarea below. */
    }

    const field = document.createElement('textarea');

    field.value = url;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';

    document.body.append(field);
    field.select();

    try {
        return document.execCommand('copy');
    } finally {
        field.remove();
    }
}

/* The confirmation happens where the reader is already looking: there is no
   room on either page for a toast. The tick is what carries it, because the
   phone bar's button is the icon and nothing else; the word follows for the
   widths that render one. */
function flash(button) {
    const label = button.querySelector('[data-share-label]');
    const icon = button.querySelector('[data-share-icon]');
    const tick = button.querySelector('[data-share-icon-copied]');
    const copied = button.dataset.shareCopied;

    if (button.dataset.shareFlashing) {
        return;
    }

    const original = label?.textContent;

    button.dataset.shareFlashing = 'true';

    if (icon && tick) {
        icon.hidden = true;
        tick.hidden = false;
    }

    if (label && copied) {
        label.textContent = copied;
    }

    setTimeout(() => {
        if (icon && tick) {
            icon.hidden = false;
            tick.hidden = true;
        }

        if (label && copied) {
            label.textContent = original;
        }

        delete button.dataset.shareFlashing;
    }, COPIED_FOR_MS);
}

export default function mountShare() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-share]');

        if (!button) {
            return;
        }

        const url = button.dataset.shareUrl || window.location.href;

        /* Called before anything is awaited: the share sheet needs the click's
           own activation and would be refused a tick later. */
        if (navigator.share) {
            try {
                await navigator.share({
                    title: button.dataset.shareTitle,
                    text: button.dataset.shareText,
                    url,
                });

                return;
            } catch (error) {
                /* A reader who closed the sheet has not asked for a copy. */
                if (error.name === 'AbortError') {
                    return;
                }
            }
        }

        /* The label only says "copied" when something was: a browser that
           refused both ways has not copied anything, and saying so is worse
           than saying nothing. */
        if (await copy(url)) {
            flash(button);
        }
    });
}
