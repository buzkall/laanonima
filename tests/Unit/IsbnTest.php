<?php

use App\Support\Isbn;

it('strips separators before validating', function(): void {
    expect(Isbn::normalize('978-84-339-2042-3'))->toBe('9788433920423')
        ->and(Isbn::normalize(' 84 339 2042 1 '))->toBe('8433920421')
        ->and(Isbn::normalize('843392042x'))->toBe('843392042X');
});

it('accepts a valid ISBN-13 however it is typed', function(string $input): void {
    expect(Isbn::isValid($input))->toBeTrue()
        ->and(Isbn::toIsbn13($input))->toBe('9788433920423');
})->with([
    '9788433920423',
    '978-84-339-2042-3',
    '978 84 339 2042 3',
]);

it('rejects a broken check digit', function(): void {
    expect(Isbn::isValid('9788433920424'))->toBeFalse()
        ->and(Isbn::toIsbn13('9788433920424'))->toBeNull();
});

it('rejects anything that is not the right length', function(?string $input): void {
    expect(Isbn::isValid($input))->toBeFalse();
})->with(['123', '', null, '97884339204233', 'not-an-isbn']);

it('converts an ISBN-10 up to its 13-digit form', function(): void {
    expect(Isbn::toIsbn13('8433920421'))->toBe('9788433920423');
});

it('converts a 978-prefixed ISBN-13 back down to 10 digits', function(): void {
    expect(Isbn::toIsbn10('9788433920423'))->toBe('8433920421');
});

it('has no ISBN-10 form for a 979 prefix', function(): void {
    $isbn13 = Isbn::toIsbn13('9791387748586');

    expect($isbn13)->toBe('9791387748586')
        ->and(Isbn::toIsbn10($isbn13))->toBeNull();
});

it('handles the X check digit of an ISBN-10', function(): void {
    expect(Isbn::isValidIsbn10('080442957X'))->toBeTrue()
        ->and(Isbn::toIsbn13('080442957X'))->toBe('9780804429573');
});
