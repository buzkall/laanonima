<?php

use App\Models\Subject;
use Database\Seeders\SubjectSeeder;
use Illuminate\Support\Facades\Http;

/*
 | Unlike BookSeeder, which fetches covers, this one must never touch the
 | network: the tree is committed so that `migrate:fresh --seed` is reproducible
 | offline and in CI.
 */
beforeEach(fn() => Http::preventStrayRequests());

it('seeds the committed tree without asking anyone for it', function(): void {
    $this->seed(SubjectSeeder::class);

    expect(Subject::query()->count())->toBeGreaterThan(100);
});

it('wires every parent it can name', function(): void {
    $this->seed(SubjectSeeder::class);

    $fantasy = Subject::query()->where('code', 'FM')->first();

    expect($fantasy)->not->toBeNull()
        ->and($fantasy->parent)->not->toBeNull()
        ->and($fantasy->parent->code)->toBe('F');

    /* A top-level subject has nobody above it, and that is not a failure. */
    expect(Subject::query()->where('code', 'F')->first()->parent_id)->toBeNull();
});

it('can be run twice without doubling the tree', function(): void {
    $this->seed(SubjectSeeder::class);
    $once = Subject::query()->count();

    $this->seed(SubjectSeeder::class);

    expect(Subject::query()->count())->toBe($once);
});

it('keeps the accents the shop writes', function(): void {
    $this->seed(SubjectSeeder::class);

    expect(Subject::query()->where('code', 'FM')->value('name'))->toBe('Fantasía');
});
