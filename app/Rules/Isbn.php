<?php

namespace App\Rules;

use App\Support\Isbn as IsbnHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Isbn implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('validation.isbn')->translate();

            return;
        }

        if (! IsbnHelper::isValid((string)$value)) {
            $fail('validation.isbn')->translate();
        }
    }
}
