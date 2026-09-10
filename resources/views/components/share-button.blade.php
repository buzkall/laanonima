@props([
    'url',
    'label',
    'copied',
    'title' => null,
    'text' => null,
    'labelled' => true,
])

{{--
 | The share control, on the book page and under La Cupida's recommendation.
 |
 | Everything it shares travels in data attributes and the behaviour lives in
 | `resources/js/share.js`, delegated from the document -- so this renders the
 | same inside a Livewire panel that is morphed on every round as it does on a
 | static page, and a reader with no JavaScript is shown no button at all
 | rather than a dead one (`hidden` until the module unhides it would be a
 | second failure mode; the button simply does nothing, which is the same thing
 | the browser does with a link it cannot follow).
 |
 | `labelled` is `true`, `false`, or `"wide"` for a label that appears only
 | once there is room for one. `false` and `"wide"` still put the word in the
 | markup and only hide it from the eye, because a button whose whole name is a
 | drawing has no name at all. `sr-only` is `position: absolute`, so the span
 | stops being a flex item and the `gap-2` costs nothing -- the icon sits alone
 | rather than beside a gap holding nothing.
 |
 | What a hidden label costs is the confirmation, which is why the tick is what
 | changes on a copy; the word follows it wherever one is rendered.
 --}}
<button
    type="button"
    data-share
    data-share-url="{{ $url }}"
    data-share-copied="{{ $copied }}"
    @if ($title) data-share-title="{{ $title }}" @endif
    @if ($text) data-share-text="{{ $text }}" @endif
    {{ $attributes->class('inline-flex cursor-pointer items-center gap-2 border-0 bg-transparent p-0 font-serif font-semibold tracking-[0.08em] uppercase transition-opacity duration-150') }}
>
    <svg data-share-icon class="size-[1.1em] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z"
        />
    </svg>

    <svg data-share-icon-copied hidden class="size-[1.1em] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
    </svg>

    <span
        data-share-label
        @class([
            'sr-only'                  => $labelled === false,
            'sr-only wide:not-sr-only' => $labelled === 'wide',
        ])
    >{{ $label }}</span>
</button>
