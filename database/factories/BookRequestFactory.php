<?php

namespace Database\Factories;

use App\Enums\BookRequestStatus;
use App\Models\BookRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookRequest>
 */
class BookRequestFactory extends Factory
{
    protected $model = BookRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'   => User::factory()->client(),
            'book_id'   => null,
            'title'     => fake()->sentence(3),
            'author'    => fake()->optional()->name(),
            'publisher' => fake()->optional()->company(),
            'isbn'      => fake()->optional()->isbn13(),
            'notes'     => fake()->optional()->sentence(),
            'status'    => BookRequestStatus::Pending,
        ];
    }

    public function handled(): static
    {
        return $this->state(['status' => BookRequestStatus::Obtained]);
    }

    public function withdrawn(): static
    {
        return $this->state(['status' => BookRequestStatus::Dropped]);
    }
}
