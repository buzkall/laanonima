<?php

use App\Actions\Books\ImportShopBook;
use App\Models\Publisher;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function shopEntry(array $overrides = []): array
{
    return array_merge([
        'ean'       => '9788412345678',
        'title'     => 'Un libro cualquiera',
        'publisher' => 'ALFAGUARA',
        'available' => true,
    ], $overrides);
}

it('files a shouted publisher the way the spine prints it', function(): void {
    $book = app(ImportShopBook::class)(shopEntry());

    expect($book->publisher?->name)->toBe('Alfaguara')
        ->and($book->publisher?->slug)->toBe('alfaguara');
});

/*
 | The shop writes the same imprint two ways -- once with the ampersand
 | double-encoded -- and the old `Str::slug($name)` filed those as
 | `plaza-amp-janes` and `plaza-janes`: two rows, one publisher. Slugging the
 | tidied name is what closes it.
 */
it('files two spellings of one imprint as a single publisher', function(): void {
    app(ImportShopBook::class)(shopEntry(['ean' => '9788412345678', 'publisher' => 'PLAZA & JANES']));
    app(ImportShopBook::class)(shopEntry(['ean' => '9788412345685', 'publisher' => 'PLAZA &amp; JANES']));

    expect(Publisher::query()->count())->toBe(1)
        ->and(Publisher::first()->name)->toBe('Plaza & Janes');
});

it('stops a publisher shouting the next time one of its books arrives', function(): void {
    $publisher = Publisher::factory()->create(['name' => 'DEBOLSILLO', 'slug' => 'debolsillo']);

    $book = app(ImportShopBook::class)(shopEntry(['publisher' => 'DEBOLSILLO']));

    expect($publisher->refresh()->name)->toBe('Debolsillo')
        ->and($book->publisher_id)->toBe($publisher->id);
});

/*
 | Only shouting is rewritten. A name a bookseller cased by hand -- or an
 | imprint that spells itself that way -- has to survive every later import.
 */
it('leaves a name that was cased on purpose alone', function(): void {
    $publisher = Publisher::factory()->create(['name' => 'Duomo ediciones', 'slug' => 'duomo-ediciones']);

    app(ImportShopBook::class)(shopEntry(['publisher' => 'Duomo ediciones']));

    expect($publisher->refresh()->name)->toBe('Duomo ediciones')
        ->and(Publisher::query()->count())->toBe(1);
});

it('recases the publishers filed before the names were tidied', function(): void {
    $shouted = Publisher::factory()->create(['name' => 'EDICIONES VERSATIL, S.L.', 'slug' => 'ediciones-versatil-s-l']);
    $typed = Publisher::factory()->create(['name' => 'Libros del Asteroide', 'slug' => 'libros-del-asteroide']);

    $this->artisan('publishers:tidy')->assertSuccessful();

    expect($shouted->refresh()->name)->toBe('Ediciones Versatil, S.L.')
        /* Renaming never moves a row: the slug, and every URL with it, is the
           same before and after. */
        ->and($shouted->slug)->toBe('ediciones-versatil-s-l')
        ->and($typed->refresh()->name)->toBe('Libros del Asteroide');
});

it('writes nothing when the tidy pass is only pretending', function(): void {
    $publisher = Publisher::factory()->create(['name' => 'TAURUS', 'slug' => 'taurus']);

    $this->artisan('publishers:tidy', ['--pretend' => true])->assertSuccessful();

    expect($publisher->refresh()->name)->toBe('TAURUS');
});
