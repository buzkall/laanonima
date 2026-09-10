/**
 * The shelf is the only page with a physics engine, so its module is fetched
 * where it is needed rather than on every page. The share control is the other
 * way round -- one delegated listener, on every page that has a button for it.
 *
 * La Cupida's deck is not in this file on purpose: this one is a `type="module"`
 * tag and therefore deferred, while Livewire injects its own bundle as a plain
 * script before `</body>`, which runs first. Alpine is already started by the
 * time anything here executes, so the deck registers itself from an inline
 * script in `resources/views/cupida/index.blade.php` instead.
 */
import mountShare from './share.js';

mountShare();

const shelf = document.querySelector('[data-shelf]');

if (shelf) {
    import('./shelf.js').then(({ default: mountShelf }) => mountShelf(shelf));
}
