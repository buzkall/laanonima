<x-layouts.shelf
    :title="__('cupida.shared.title')"
    :description="$recommendation->matchLine ?? str($recommendation->pitch)->limit(155)"
    :palette="$palette"
    :og-image="$shareCard"
    :og-image-alt="__('books.fields.cover') . ': ' . $recommendation->title"
    {{-- One page per game played, each about a book that already has a page of
         its own, and none of them meant for a stranger who did not get the
         link. --}}
    :indexable="false"
>
    {{-- The same panel the reader saw at the end of their session, drawn from
         the row it was written to. Not `fits-viewport`: that page is a screen
         to be filled and this one is a document to be read, so the pitch and
         the synopsis are simply open and nothing is measured against the
         window. --}}
    <section
        class="bg-[var(--card)] px-[clamp(22px,5vw,80px)] pt-[clamp(40px,6vw,88px)] pb-[clamp(56px,7vw,104px)] text-[var(--on-card)]"
        style="--card: {{ $palette->background }}; --on-card: {{ $palette->foreground }}"
    >
        <div class="mx-auto w-full max-w-[1040px]">
            <x-cupida-result
                :recommendation="$recommendation"
                :kicker="__('cupida.shared.kicker')"
                :clamp="false"
            >
                @if ($recommendation->book)
                    <a
                        href="{{ $recommendation->url }}"
                        class="bg-[var(--on-card)] px-6 py-3 text-[18px] font-semibold tracking-[0.08em] text-[var(--card)] uppercase no-underline transition-opacity duration-150 hover:opacity-85"
                    >
                        {{ __('cupida.result.read_more') }}
                    </a>
                @endif

                {{-- The reason this page is worth landing on: somebody was sent
                     a book by a librera who does not exist, and the only useful
                     thing to do about that is play. It is the solid button when
                     the book is not one of ours, because then it is the only
                     thing on the page to press. --}}
                <a
                    href="{{ route('cupida') }}"
                    @class([
                        'text-[18px] font-serif font-semibold tracking-[0.08em] uppercase no-underline transition-opacity duration-150',
                        'border-0 border-b-2 border-current pb-[3px] text-[var(--on-card)] hover:opacity-65' => $recommendation->book,
                        'bg-[var(--on-card)] px-6 py-3 text-[var(--card)] hover:opacity-85' => ! $recommendation->book,
                    ])
                >
                    {{ __('cupida.shared.cta') }}
                </a>
            </x-cupida-result>

            <p class="mt-10 mb-0 max-w-[52ch] border-t border-[var(--rule)] pt-6 text-[15px] italic opacity-75">
                {{ __('cupida.shared.lead') }}
            </p>
        </div>
    </section>
</x-layouts.shelf>
