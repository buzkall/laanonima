<?php

namespace Database\Factories;

use App\Models\Author;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Author>
 */
class AuthorFactory extends Factory
{
    protected $model = Author::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->name(),

            /*
             | Derived from whatever name the caller ends up with, not from the
             | one above: a test that overrides the name would otherwise leave a
             | slug naming a different person, which the listing searches.
             */
            'slug' => fn(array $attributes): string => Str::slug($attributes['name']),
            'bio'  => fake()->optional()->paragraph(),
        ];
    }
}
