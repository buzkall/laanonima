<?php

namespace App\Http\Requests;

use App\Rules\Isbn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What a reader has to tell us before we can go and look for a book.
 *
 * Only the title is required: everything else narrows the search down and is
 * optional on purpose, because the form has to be answerable by someone who
 * half remembers a title. We already know who they are -- the form is behind a
 * sign-in -- and the telephone is asked for only while their account has none.
 */
class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone'     => ['nullable', 'string', 'max:60'],
            'title'     => ['required', 'string', 'max:255'],
            'author'    => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'isbn'      => ['nullable', 'string', 'max:20', new Isbn],
            'notes'     => ['nullable', 'string', 'max:2000'],
            'book_id'   => ['nullable', Rule::exists('books', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone'     => __('book_requests.fields.phone'),
            'title'     => __('book_requests.fields.title'),
            'author'    => __('book_requests.fields.author'),
            'publisher' => __('book_requests.fields.publisher'),
            'isbn'      => __('book_requests.fields.isbn'),
            'notes'     => __('book_requests.fields.notes'),
        ];
    }
}
