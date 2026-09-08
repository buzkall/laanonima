<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * The THEMA subject tree, from the file `books:import-subjects` writes.
 *
 * Unlike BookSeeder this touches no network at all: the tree is committed, so
 * `migrate:fresh --seed` is reproducible offline and in CI, and re-running it
 * changes nothing.
 *
 * Two passes, because a parent is not guaranteed to be inserted before its
 * child -- the file is sorted by code, which usually gets it right and cannot
 * be relied on to. The first pass writes every subject, the second wires the
 * parents once every code has an id.
 */
class SubjectSeeder extends Seeder
{
    /** Rows per statement. Big enough to be quick, small enough for SQLite. */
    private const int CHUNK = 500;

    public function run(): void
    {
        $subjects = $this->read();

        if ($subjects === []) {
            $this->command->warn('No subjects to seed. Run `php artisan books:import-subjects` first.');

            return;
        }

        $now = now();

        /* upsert() writes straight to the table, so the `saving` hook that
           derives a slug never fires -- which is why the file carries one and
           this passes it through rather than leaving it to the model. */

        collect($subjects)
            ->map(fn(array $subject): array => [
                'code'       => (string)$subject['code'],
                'name'       => (string)$subject['name'],
                'slug'       => (string)$subject['slug'],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->chunk(self::CHUNK)
            ->each(fn($chunk) => Subject::query()->upsert(
                $chunk->values()->all(),
                uniqueBy: ['code'],
                update: ['name', 'slug', 'updated_at'],
            ));

        $this->wireParents($subjects);

        $this->command->info(sprintf('%d subjects seeded.', count($subjects)));
    }

    /**
     * One statement per parent rather than one per child: the tree is a couple
     * of thousand nodes hanging off a few hundred parents, and the difference
     * is two thousand queries.
     *
     * @param  array<int, array<string, mixed>>  $subjects
     */
    private function wireParents(array $subjects): void
    {
        /** @var array<string, int> $ids */
        $ids = Subject::query()->pluck('id', 'code')->all();

        collect($subjects)
            ->filter(fn(array $subject): bool => is_string($subject['parent'] ?? null)
                && isset($ids[$subject['parent']]))
            ->groupBy('parent')
            ->each(function($children, string $parent) use ($ids): void {
                Subject::query()
                    ->whereIn('code', $children->pluck('code')->all())
                    ->update(['parent_id' => $ids[$parent]]);
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function read(): array
    {
        $path = database_path('seeders/data/subjects.json');

        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), associative: true);

        return is_array($decoded) ? $decoded : [];
    }
}
