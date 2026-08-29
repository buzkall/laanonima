<?php

namespace Database\Factories;

use App\Models\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Publisher>
 */
class PublisherFactory extends Factory
{
    protected $model = Publisher::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Anagrama', 'Tusquets', 'Seix Barral', 'Alfaguara', 'Acantilado',
            'Galaxia Gutenberg', 'Impedimenta', 'Libros del Asteroide', 'Blackie Books',
            'Sexto Piso', 'Periférica', 'Errata Naturae', 'Nórdica Libros',
        ]) . ' ' . Str::upper(fake()->unique()->lexify('??'));

        return [
            'name' => $name,

            /*
             | Derived from whatever name the caller ends up with, not from the
             | one above: a test that overrides the name would otherwise leave a
             | slug quoting a different publisher, which the listing searches.
             */
            'slug'        => fn(array $attributes): string => Str::slug($attributes['name']),
            'description' => fake()->optional()->paragraph(),
            'website'     => fake()->optional()->url(),
        ];
    }
}
