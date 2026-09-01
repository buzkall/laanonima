/**
 * The shelf is the only page with a script, and it pulls in a physics engine,
 * so it is fetched only where it is needed rather than on every page.
 */
const shelf = document.querySelector('[data-shelf]');

if (shelf) {
    import('./shelf.js').then(({ default: mountShelf }) => mountShelf(shelf));
}
