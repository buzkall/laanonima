/**
 * The header's search box opens from a `<details>`, which knows nothing about
 * focus: without this a reader clicks the magnifier and still has to click the
 * field. `autofocus` does not help either -- it only applies on page load, not
 * when a disclosure opens.
 *
 * `toggle` does not bubble, so the listener sits on the document in the capture
 * phase, the same delegated shape as the share control.
 */
export default function mountSearch() {
    document.addEventListener(
        "toggle",
        (event) => {
            const disclosure = event.target;

            if (
                !(disclosure instanceof HTMLDetailsElement) ||
                !disclosure.matches("[data-site-search]") ||
                !disclosure.open
            ) {
                return;
            }

            disclosure.querySelector('input[name="q"]')?.focus();
        },
        true,
    );
}
