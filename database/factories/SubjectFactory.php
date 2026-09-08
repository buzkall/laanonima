<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /* A plausible THEMA code: a top-level letter and a couple more. */
        $code = fake()->unique()->regexify('[A-Z]{3}');

        return [
            'code'      => $code,
            'name'      => fake()->words(2, true),
            'slug'      => null,
            'parent_id' => null,
        ];
    }

    /**
     * A subject filed under another, with the code to match -- the prefix is
     * what `withinTree()` relies on, so a factory that ignored it would build
     * trees the scopes cannot find.
     */
    public function under(Subject $parent): static
    {
        return $this->state(fn(): array => [
            'code'      => $parent->code . fake()->unique()->randomLetter(),
            'parent_id' => $parent->id,
        ]);
    }
}
