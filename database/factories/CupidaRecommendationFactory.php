<?php

namespace Database\Factories;

use App\Models\CupidaRecommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CupidaRecommendation>
 */
class CupidaRecommendationFactory extends Factory
{
    protected $model = CupidaRecommendation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            /* Nobody, which is the ordinary case: the page asks for nothing. */
            'user_id'       => null,
            'seed'          => fake()->numberBetween(1, 999999),
            'likes'         => ['theme:FM', 'author:le-guin-ursula-k', 'mood:escape'],
            'passes'        => ['theme:WB', 'mood:laugh'],
            'ean'           => fake()->isbn13(),
            'title'         => fake()->sentence(3),
            'author'        => fake()->name(),
            'book_id'       => null,
            'pitch'         => fake()->paragraph(),
            'match_line'    => 'Porque dijiste que sí a la fantasía.',
            'written'       => true,
            'model'         => 'claude-haiku-4-5',
            'input_tokens'  => 4200,
            'output_tokens' => 180,
            'cost'          => 0.005100,

            /* Disagreeing by default: the interesting row is the one where the
               model went past the top of the list, and a factory of ones would
               make a passing test out of a model that never chose anything. */
            'shortlist_ean'    => fake()->isbn13(),
            'shortlist_title'  => fake()->sentence(3),
            'shortlist_author' => fake()->name(),
            'shortlist_rank'   => 4,
        ];
    }

    /**
     * The canned line: no key, a provider that failed, or a limit reached.
     */
    public function fallback(): static
    {
        return $this->state([
            'written'       => false,
            'model'         => null,
            'match_line'    => null,
            'pitch'         => __('cupida.result.fallback_pitch'),
            'input_tokens'  => null,
            'output_tokens' => null,
            'cost'          => null,
            /* The canned line is the top of the shortlist, always. */
            'shortlist_rank' => 1,
        ]);
    }
}
