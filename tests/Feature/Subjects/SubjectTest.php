<?php

use App\Models\Book;
use App\Models\Subject;

it('finds everything filed under a subject, however deep', function(): void {
    $fiction = Subject::factory()->create(['code' => 'F', 'name' => 'Ficción y temas afines']);
    $fantasy = Subject::factory()->create(['code' => 'FM', 'name' => 'Fantasía', 'parent_id' => $fiction->id]);
    $epic = Subject::factory()->create(['code' => 'FMM', 'name' => 'Fantasía épica', 'parent_id' => $fantasy->id]);
    $crime = Subject::factory()->create(['code' => 'FF', 'name' => 'Crímenes y misterio', 'parent_id' => $fiction->id]);

    $under = fn(string $code): array => Subject::query()->withinTree($code)->pluck('code')->sort()->values()->all();

    expect($under('FM'))->toBe(['FM', 'FMM'])
        ->and($under('F'))->toBe(['F', 'FF', 'FM', 'FMM'])
        ->and($under('FF'))->toBe(['FF']);

    /* The point of the prefix: no recursion, and the crime shelf is not fantasy. */
    expect($under('FM'))->not->toContain($crime->code)
        ->and($under('FMM'))->toBe([$epic->code]);
});

it('writes out where a subject sits, for someone reading a form', function(): void {
    $fiction = Subject::factory()->create(['name' => 'Ficción y temas afines']);
    $fantasy = Subject::factory()->create(['name' => 'Fantasía'])->parent()->associate($fiction);
    $fantasy->save();

    expect($fantasy->fresh()->path())->toBe('Ficción y temas afines › Fantasía');
});

it('derives a slug from the code and the name', function(): void {
    $subject = Subject::factory()->create(['code' => 'FMM', 'name' => 'Fantasía épica', 'slug' => null]);

    expect($subject->slug)->toBe('fmm-fantasia-epica');
});

it('lets go of its books rather than taking them with it', function(): void {
    $subject = Subject::factory()->create();
    $book = Book::factory()->for($subject)->create();

    $subject->delete();

    expect($book->fresh())->not->toBeNull()
        ->and($book->fresh()->subject_id)->toBeNull();
});
