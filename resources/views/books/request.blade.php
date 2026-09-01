@php
    /* The same form both ways in: from a book page it arrives filled in with
       that book, from the footer it arrives empty. Only the copy changes. */
    $sent = session('book_request_sent');
    $user = auth()->user();

    /* The account already holds a name, an address and -- once it has been
       given once -- a telephone, so the form asks for none of them again. */
    $asksForPhone = blank($user->phone);

    $defaults = [
        'title'     => old('title', $book?->title),
        'author'    => old('author', $book?->authors_line),
        'publisher' => old('publisher', $book?->publisher?->name),
        'isbn'      => old('isbn', $book?->isbn13),
        'notes'     => old('notes'),
        'phone'     => old('phone'),
    ];

    $field = 'mt-2 w-full border border-[color-mix(in_srgb,var(--color-ink)_25%,transparent)] bg-paper px-4 py-3 font-serif text-[19px] text-ink outline-none transition-colors duration-150 focus:border-[var(--accent)]';
    $label = 'block text-[13px] font-semibold uppercase tracking-[0.16em]';
    $error = 'mt-2 mb-0 text-[16px] italic text-[var(--accent)]';
@endphp

<x-layouts.shelf
    :title="__('book_requests.public.kicker')"
    :description="__('book_requests.public.intro')"
    :palette="$palette"
    :footer-cta="false"
>

<section class="bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[clamp(48px,7vw,104px)] pb-[clamp(52px,6vw,96px)] text-[var(--on-cover)]">
    <p class="m-0 mb-[18px] text-[14px] font-bold uppercase tracking-[0.26em]">
        {{ $book ? __('book_requests.public.book_kicker') : __('book_requests.public.kicker') }}
    </p>

    <h1 class="m-0 max-w-[16ch] text-balance font-display text-[clamp(44px,6vw,96px)]/[0.96] font-normal tracking-[-0.01em]">
        {{ $book ? $book->title : __('book_requests.public.heading') }}
    </h1>

    <p class="mt-9 mb-0 max-w-[620px] border-t border-[var(--rule)] pt-8 text-[clamp(20px,2.1vw,26px)]/[1.5] italic">
        {{ $book ? __('book_requests.public.book_intro') : __('book_requests.public.intro') }}
    </p>

    <a href="{{ $book ? route('books.show', $book) : route('home') }}" class="mt-8 inline-block border-b-2 border-current pb-[3px] text-[15px] font-semibold uppercase tracking-[0.12em] transition-opacity duration-150 hover:opacity-65">
        {{ $book ? __('books.public.shelf_back') : __('book_requests.public.back') }}
    </a>
</section>

<main class="bg-paper px-[clamp(22px,5vw,80px)] pt-[clamp(44px,5vw,76px)] pb-[clamp(56px,6vw,96px)] text-ink">
    <div class="mx-auto max-w-[720px]">
        @if ($sent)
            {{-- The receipt: the shelf is one page back, so there is nothing to do here but read it. --}}
            <div class="border-l-4 border-[var(--accent)] pl-6">
                <p class="m-0 font-display text-[clamp(30px,4vw,44px)]/[1.1]">{{ __('book_requests.public.sent.heading') }}</p>
                <p class="mt-4 mb-0 text-[20px] italic">{{ __('book_requests.public.sent.body', ['title' => $sent]) }}</p>
            </div>

            <a href="{{ route('home') }}" class="mt-10 inline-block bg-[var(--accent)] px-6 py-3 text-[18px] font-semibold uppercase tracking-[0.08em] text-paper transition-opacity duration-150 hover:opacity-85">
                {{ __('book_requests.public.back') }}
            </a>
        @else
            <p class="mt-0 mb-10 text-[18px] italic opacity-75">
                {{ __('book_requests.public.required') }}
                {{ __('book_requests.public.signed_in_as', ['email' => $user->email]) }}
            </p>

            <form method="POST" action="{{ route('book-requests.store') }}" class="grid grid-cols-1 gap-7 wide:grid-cols-2">
                @csrf

                @if ($book)
                    <input type="hidden" name="book_id" value="{{ $book->id }}" />
                @endif

                <div class="wide:col-span-2">
                    <label for="title" class="{{ $label }}">{{ __('book_requests.fields.title') }}</label>
                    <input id="title" name="title" type="text" required maxlength="255"
                        value="{{ $defaults['title'] }}"
                        class="{{ $field }}" />
                    @error('title') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="author" class="{{ $label }}">
                        {{ __('book_requests.fields.author') }} <span class="font-normal normal-case tracking-normal opacity-60">{{ __('book_requests.public.optional') }}</span>
                    </label>
                    <input id="author" name="author" type="text" maxlength="255"
                        value="{{ $defaults['author'] }}"
                        class="{{ $field }}" />
                    @error('author') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="publisher" class="{{ $label }}">
                        {{ __('book_requests.fields.publisher') }} <span class="font-normal normal-case tracking-normal opacity-60">{{ __('book_requests.public.optional') }}</span>
                    </label>
                    <input id="publisher" name="publisher" type="text" maxlength="255"
                        value="{{ $defaults['publisher'] }}"
                        class="{{ $field }}" />
                    @error('publisher') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div class="wide:col-span-2">
                    <label for="isbn" class="{{ $label }}">
                        {{ __('book_requests.fields.isbn') }} <span class="font-normal normal-case tracking-normal opacity-60">{{ __('book_requests.public.optional') }}</span>
                    </label>
                    <input id="isbn" name="isbn" type="text" inputmode="numeric" maxlength="20"
                        value="{{ $defaults['isbn'] }}"
                        class="{{ $field }}" />
                    @error('isbn') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div class="wide:col-span-2">
                    <label for="notes" class="{{ $label }}">
                        {{ __('book_requests.fields.notes') }} <span class="font-normal normal-case tracking-normal opacity-60">{{ __('book_requests.public.optional') }}</span>
                    </label>
                    <textarea id="notes" name="notes" rows="4" maxlength="2000"
                        class="{{ $field }}">{{ $defaults['notes'] }}</textarea>
                    @error('notes') <p class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                @if ($asksForPhone)
                    {{-- Asked for once and kept on the account, so a reader who
                         has already given us a number never sees this again. --}}
                    <div class="wide:col-span-2">
                        <label for="phone" class="{{ $label }}">
                            {{ __('book_requests.fields.phone') }} <span class="font-normal normal-case tracking-normal opacity-60">{{ __('book_requests.public.optional') }}</span>
                        </label>
                        <input id="phone" name="phone" type="tel" maxlength="60" autocomplete="tel"
                            value="{{ $defaults['phone'] }}"
                            class="{{ $field }}" />
                        <p class="mt-2 mb-0 text-[16px] italic opacity-70">{{ __('book_requests.public.phone_note') }}</p>
                        @error('phone') <p class="{{ $error }}">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="wide:col-span-2">
                    <button type="submit" class="bg-[var(--accent)] px-6 py-3 text-[18px] font-semibold uppercase tracking-[0.08em] text-paper transition-opacity duration-150 hover:opacity-85">
                        {{ __('book_requests.public.submit') }}
                    </button>
                </div>
            </form>
        @endif
    </div>
</main>

</x-layouts.shelf>
