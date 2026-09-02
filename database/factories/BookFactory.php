<?php

namespace Database\Factories;

use App\Enums\BookAvailability;
use App\Enums\BookBinding;
use App\Enums\BookLanguage;
use App\Enums\ContributorRole;
use App\Models\Book;
use App\Models\Publisher;
use App\Support\Isbn;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use WeakMap;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    /**
     * The title page each made book is waiting for, keyed by the instance.
     *
     * `contributors` is not a column any more, but a test still reads best as
     * `Book::factory()->create(['contributors' => [['name' => ..., 'role' => 'author']]])`,
     * so the key is lifted out of the attributes before the model is built
     * and filed as rows once it has an id.
     *
     * @var WeakMap<Book, array<int, array{name: string, role: ContributorRole|string}>>
     */
    private static ?WeakMap $pendingContributors = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::ucfirst(rtrim(fake()->sentence(fake()->numberBetween(2, 5)), '.'));
        $publishedOn = fake()->dateTimeBetween('-25 years', 'now');

        return [
            'isbn13'       => $this->isbn13(),
            'slug'         => Str::slug($title) . '-' . fake()->unique()->numerify('######'),
            'title'        => $title,
            'subtitle'     => fake()->optional(0.3)->sentence(4),
            'contributors' => [
                ['name' => fake()->name(), 'role' => ContributorRole::Author->value],
            ],
            'publisher_id'    => Publisher::factory(),
            'published_on'    => $publishedOn,
            'published_year'  => (int)$publishedOn->format('Y'),
            'binding'         => fake()->randomElement([BookBinding::Paperback, BookBinding::Hardback, BookBinding::Pocket]),
            'pages'           => fake()->numberBetween(80, 900),
            'language'        => BookLanguage::Spa,
            'synopsis'        => fake()->paragraph(),
            'price_cents'     => fake()->numberBetween(750, 3500),
            'stock'           => fake()->numberBetween(0, 12),
            'availability'    => BookAvailability::Available,
            'is_featured'     => false,
            'is_active'       => true,
            'metadata_source' => 'manual',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function(Book $book): void {
            $contributors = $this->pending()[$book] ?? [];
            unset($this->pending()[$book]);

            $book->syncContributors($contributors);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): Book
    {
        $contributors = $attributes['contributors'] ?? [];
        unset($attributes['contributors']);

        $book = parent::newModel($attributes);
        $this->pending()[$book] = $contributors;

        return $book;
    }

    public function featured(): static
    {
        return $this->state(fn(): array => ['is_featured' => true]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn(): array => [
            'stock'        => 0,
            'availability' => BookAvailability::OutOfStock,
        ]);
    }

    public function withTranslator(): static
    {
        return $this->state(fn(array $attributes): array => [
            'contributors' => [
                ...$attributes['contributors'],
                ['name' => fake()->name(), 'role' => ContributorRole::Translator->value],
            ],
        ]);
    }

    /**
     * @return WeakMap<Book, array<int, array{name: string, role: ContributorRole|string}>>
     */
    private function pending(): WeakMap
    {
        return self::$pendingContributors ??= new WeakMap;
    }

    /**
     * A syntactically valid ISBN-13, check digit included.
     */
    private function isbn13(): string
    {
        do {
            $body = '978' . fake()->numerify('#########');
            $candidate = Isbn::toIsbn13($body . $this->checkDigit($body));
        } while ($candidate === null);

        return $candidate;
    }

    private function checkDigit(string $first12): string
    {
        $sum = 0;

        foreach (str_split($first12) as $position => $digit) {
            $sum += (int)$digit * ($position % 2 === 0 ? 1 : 3);
        }

        return (string)((10 - $sum % 10) % 10);
    }
}
