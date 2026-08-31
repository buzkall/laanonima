{{-- @formatter:off --}}
<x-mail::message>
# {{ __('book_requests.mail.withdrawn.heading') }}

{{ __('book_requests.mail.withdrawn.intro', ['name' => $bookRequest->user->name]) }}

**{{ __('book_requests.fields.title') }}:** {{ $bookRequest->title }}
@if ($bookRequest->author)
**{{ __('book_requests.fields.author') }}:** {{ $bookRequest->author }}
@endif

**{{ __('user.fields.email') }}:** {{ $bookRequest->user->email }}

<x-mail::button :url="$url">
{{ __('book_requests.mail.withdrawn.action') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
{{-- @formatter:on --}}
