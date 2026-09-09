/**
 * The shelf is the only page with a script here, and it pulls in a physics
 * engine, so it is fetched only where it is needed rather than on every page.
 *
 * La Cupida's deck is not in this file on purpose: this one is a `type="module"`
 * tag and therefore deferred, while Livewire injects its own bundle as a plain
 * script before `</body>`, which runs first. Alpine is already started by the
 * time anything here executes, so the deck registers itself from an inline
 * script in `resources/views/cupida/index.blade.php` instead.
 */
const shelf = document.querySelector('[data-shelf]');

if (shelf) {
    import('./shelf.js').then(({ default: mountShelf }) => mountShelf(shelf));
}
