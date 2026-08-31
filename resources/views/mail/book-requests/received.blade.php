{{-- @formatter:off --}}
<x-mail::message>
# {{ __('book_requests.mail.received.heading') }}

{{ __('book_requests.mail.received.intro', ['name' => $bookRequest->user->name]) }}

**{{ __('book_requests.fields.title') }}:** {{ $bookRequest->title }}
@if ($bookRequest->author)
**{{ __('book_requests.fields.author') }}:** {{ $bookRequest->author }}
@endif
@if ($bookRequest->publisher)
**{{ __('book_requests.fields.publisher') }}:** {{ $bookRequest->publisher }}
@endif
@if ($bookRequest->isbn)
**{{ __('book_requests.fields.isbn') }}:** {{ $bookRequest->isbn }}
@endif
@if ($bookRequest->book)
**{{ __('book_requests.fields.book_id') }}:** [{{ $bookRequest->book->title }}]({{ route('books.show', $bookRequest->book) }})
@endif

**{{ __('user.fields.name') }}:** {{ $bookRequest->user->name }}
**{{ __('user.fields.email') }}:** {{ $bookRequest->user->email }}
@if ($bookRequest->user->phone)
**{{ __('user.fields.phone') }}:** {{ $bookRequest->user->phone }}
@endif

@if ($bookRequest->notes)
> {{ $bookRequest->notes }}
@endif

<x-mail::button :url="$url">
{{ __('book_requests.mail.received.action') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
{{-- @formatter:on --}}
