@php
    /* The same form every way in: from a book page it arrives filled in with
       that book, from the footer it arrives empty. Only the copy changes.

       A book still on the table is the third way in -- "guardadmelo" -- and it
       is the same note to the bookseller, so it is the same form. It only has
       to stop promising to order from the distributor a book we already hold. */
    $user = auth()->user();
    $held = (bool) $book?->stock;

    /* Once the request is in, the receipt IS the page: the colored band said
       "dinos que buscas" over a receipt that said the opposite, and each of
       them offered its own way back to the shelf. So the band carries the
       receipt and the form's half of the page goes away entirely. */
    $sentCover = $sentBook?->coverUrl();

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
    :title="$sent ? __('book_requests.public.sent.kicker') : __('book_requests.public.kicker')"
    :description="__('book_requests.public.intro')"
    :palette="$palette"
    :footer-cta="false"
>
    @if ($sent)
        <section class="flex grow flex-col justify-center bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[clamp(48px,7vw,104px)] pb-[clamp(52px,6vw,96px)] text-[var(--on-cover)]">
            <div @class([
                'flex flex-col-reverse items-start gap-[clamp(28px,4vw,64px)]',
                'wide:flex-row wide:items-center' => $sentCover,
            ])>
                <div class="min-w-0 grow">
                    <p class="m-0 mb-[18px] text-[14px] font-bold tracking-[0.26em] uppercase">
                        {{ __('book_requests.public.sent.kicker') }}
                    </p>

                    <h1 class="font-display m-0 max-w-[16ch] text-[clamp(44px,6vw,96px)]/[0.96] font-normal tracking-[-0.01em] text-balance">
                        {{ __('book_requests.public.sent.heading') }}
                    </h1>

                    <p class="mt-9 mb-0 max-w-[620px] border-t border-[var(--rule)] pt-8 text-[clamp(20px,2.1vw,26px)]/[1.5] italic">
                        {{ __('book_requests.public.sent.body', ['title' => $sent->title]) }}
                    </p>

                    <a
                        href="{{ route('home') }}"
                        class="mt-8 inline-block border-b-2 border-current pb-[3px] text-[15px] font-semibold tracking-[0.12em] uppercase transition-opacity duration-150 hover:opacity-65"
                    >
                        {{ __('book_requests.public.back') }}
                    </a>
                </div>

                @if ($sentCover)
                    {{-- The book we are going after, so the receipt is about a
                     thing rather than about a sentence. Only a book we hold a
                     record of has one: either the request came from its page,
                     or the ISBN the reader typed found it. --}}
                    <img
                        src="{{ $sentCover }}"
                        alt="{{ __('books.fields.cover') }}: {{ $sentBook->title }}"
                        class="wide:mx-0 wide:w-[clamp(180px,20vw,260px)] mx-auto block h-auto w-full max-w-[200px] shrink-0 shadow-[0_2px_8px_rgba(33,21,17,0.18),0_26px_60px_rgba(33,21,17,0.34)]"
                    />
                @endif
            </div>
        </section>
    @else
        <section class="bg-[var(--cover)] px-[clamp(22px,5vw,80px)] pt-[clamp(48px,7vw,104px)] pb-[clamp(52px,6vw,96px)] text-[var(--on-cover)]">
            <p class="m-0 mb-[18px] text-[14px] font-bold tracking-[0.26em] uppercase">
                @if ($held)
                    {{ __('book_requests.public.held_kicker') }}
                @else
                    {{ $book ? __('book_requests.public.book_kicker') : __('book_requests.public.kicker') }}
                @endif
            </p>

            <h1 class="font-display m-0 max-w-[16ch] text-[clamp(44px,6vw,96px)]/[0.96] font-normal tracking-[-0.01em] text-balance">
                {{ $book ? $book->title : __('book_requests.public.heading') }}
            </h1>

            <p class="mt-9 mb-0 max-w-[620px] border-t border-[var(--rule)] pt-8 text-[clamp(20px,2.1vw,26px)]/[1.5] italic">
                @if ($held)
                    {{ __('book_requests.public.held_intro') }}
                @else
                    {{ $book ? __('book_requests.public.book_intro') : __('book_requests.public.intro') }}
                @endif
            </p>

            <a
                href="{{ $book ? route('books.show', $book) : route('home') }}"
                class="mt-8 inline-block border-b-2 border-current pb-[3px] text-[15px] font-semibold tracking-[0.12em] uppercase transition-opacity duration-150 hover:opacity-65"
            >
                {{ $book ? __('books.public.shelf_back') : __('book_requests.public.back') }}
            </a>
        </section>

        <main class="bg-paper text-ink px-[clamp(22px,5vw,80px)] pt-[clamp(44px,5vw,76px)] pb-[clamp(56px,6vw,96px)]">
            <div class="mx-auto max-w-[720px]">
                <p class="mt-0 mb-10 text-[18px] italic opacity-75">
                    {{ __('book_requests.public.required') }} {{ __('book_requests.public.signed_in_as', ['email' => $user->email]) }}
                </p>

                <form
                    method="POST"
                    action="{{ route('book-requests.store') }}"
                    class="wide:grid-cols-2 grid grid-cols-1 gap-7"
                >
                    @csrf

                    @if ($book)
                        <input type="hidden" name="book_id" value="{{ $book->id }}" />
                    @endif

                    <div class="wide:col-span-2">
                        <label for="title" class="{{ $label }}">{{ __('book_requests.fields.title') }}</label>
                        <input
                            id="title"
                            name="title"
                            type="text"
                            required
                            maxlength="255"
                            value="{{ $defaults['title'] }}"
                            class="{{ $field }}"
                        />
                        @error('title')
                            <p class="{{ $error }}">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="author" class="{{ $label }}">
                            {{ __('book_requests.fields.author') }}
                            <span class="font-normal tracking-normal normal-case opacity-60">{{ __('book_requests.public.optional') }}</span>
                        </label>
                        <input
                            id="author"
                            name="author"
                            type="text"
                            maxlength="255"
                            value="{{ $defaults['author'] }}"
                            class="{{ $field }}"
                        />
                        @error('author')
                            <p class="{{ $error }}">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="publisher" class="{{ $label }}">
                            {{ __('book_requests.fields.publisher') }}
                            <span class="font-normal tracking-normal normal-case opacity-60">{{ __('book_requests.public.optional') }}</span>
                        </label>
                        <input
                            id="publisher"
                            name="publisher"
                            type="text"
                            maxlength="255"
                            value="{{ $defaults['publisher'] }}"
                            class="{{ $field }}"
                        />
                        @error('publisher')
                            <p class="{{ $error }}">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="wide:col-span-2">
                        <label for="isbn" class="{{ $label }}">
                            {{ __('book_requests.fields.isbn') }}
                            <span class="font-normal tracking-normal normal-case opacity-60">{{ __('book_requests.public.optional') }}</span>
                        </label>
                        <input
                            id="isbn"
                            name="isbn"
                            type="text"
                            inputmode="numeric"
                            maxlength="20"
                            value="{{ $defaults['isbn'] }}"
                            class="{{ $field }}"
                        />
                        @error('isbn')
                            <p class="{{ $error }}">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="wide:col-span-2">
                        <label for="notes" class="{{ $label }}">
                            {{ __('book_requests.fields.notes') }}
                            <span class="font-normal tracking-normal normal-case opacity-60">{{ __('book_requests.public.optional') }}</span>
                        </label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="4"
                            maxlength="2000"
                            class="{{ $field }}"
                        >{{ $defaults['notes'] }}</textarea>
                        @error('notes')
                            <p class="{{ $error }}">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($asksForPhone)
                        {{-- Asked for once and kept on the account, so a reader who
                         has already given us a number never sees this again. --}}
                        <div class="wide:col-span-2">
                            <label for="phone" class="{{ $label }}">
                                {{ __('book_requests.fields.phone') }}
                                <span class="font-normal tracking-normal normal-case opacity-60">{{ __('book_requests.public.optional') }}</span>
                            </label>
                            <input
                                id="phone"
                                name="phone"
                                type="tel"
                                maxlength="60"
                                autocomplete="tel"
                                value="{{ $defaults['phone'] }}"
                                class="{{ $field }}"
                            />
                            <p class="mt-2 mb-0 text-[16px] italic opacity-70">
                                {{ __('book_requests.public.phone_note') }}
                            </p>
                            @error('phone')
                                <p class="{{ $error }}">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <div class="wide:col-span-2">
                        <button
                            type="submit"
                            class="text-paper bg-[var(--accent)] px-6 py-3 text-[18px] font-semibold tracking-[0.08em] uppercase transition-opacity duration-150 hover:opacity-85"
                        >
                            {{ __('book_requests.public.submit') }}
                        </button>
                    </div>
                </form>
            </div>
        </main>
    @endif
</x-layouts.shelf>
