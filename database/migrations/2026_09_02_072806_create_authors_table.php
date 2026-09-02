<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function(Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->timestamps();
        });

        Schema::create('book_contributors', function(Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->unsignedSmallInteger('position')->default(0);

            $table->unique(['book_id', 'author_id', 'role']);
        });

        $this->moveContributorsIntoRows();

        Schema::table('books', function(Blueprint $table): void {
            $table->dropColumn(['contributors', 'author_slugs']);
        });
    }

    public function down(): void
    {
        Schema::table('books', function(Blueprint $table): void {
            $table->jsonb('contributors')->nullable()->after('original_title');
            $table->jsonb('author_slugs')->nullable()->after('authors_line');
        });

        $this->moveRowsBackIntoContributors();

        Schema::dropIfExists('book_contributors');
        Schema::dropIfExists('authors');
    }

    /**
     * Every name in the contributors JSON becomes an author row, keyed by
     * slug so the same person on two books is one record, and every entry a
     * contribution in the order it was written.
     */
    private function moveContributorsIntoRows(): void
    {
        DB::table('books')->select('id', 'contributors')->orderBy('id')->chunk(200, function($books): void {
            foreach ($books as $book) {
                $contributors = json_decode((string)$book->contributors, true) ?: [];
                $position = 0;

                foreach ($contributors as $contributor) {
                    $name = trim((string)($contributor['name'] ?? ''));
                    $role = $contributor['role'] ?? null;

                    if ($name === '' || blank($role)) {
                        continue;
                    }

                    $slug = Str::slug($name);
                    $authorId = DB::table('authors')->where('slug', $slug)->value('id')
                        ?? DB::table('authors')->insertGetId([
                            'name'       => $name,
                            'slug'       => $slug,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                    DB::table('book_contributors')->insertOrIgnore([
                        'book_id'   => $book->id,
                        'author_id' => $authorId,
                        'role'      => $role,
                        'position'  => ++$position,
                    ]);
                }
            }
        });
    }

    private function moveRowsBackIntoContributors(): void
    {
        $rows = DB::table('book_contributors')
            ->join('authors', 'authors.id', '=', 'book_contributors.author_id')
            ->orderBy('book_contributors.book_id')
            ->orderBy('book_contributors.position')
            ->orderBy('book_contributors.id')
            ->get(['book_contributors.book_id', 'authors.name', 'book_contributors.role'])
            ->groupBy('book_id');

        foreach ($rows as $bookId => $contributors) {
            $people = $contributors
                ->map(fn(object $row): array => ['name' => $row->name, 'role' => $row->role])
                ->values()
                ->all();

            $slugs = $contributors
                ->where('role', 'author')
                ->map(fn(object $row): string => Str::slug($row->name))
                ->unique()
                ->values()
                ->all();

            DB::table('books')->where('id', $bookId)->update([
                'contributors' => json_encode($people),
                'author_slugs' => json_encode($slugs),
            ]);
        }
    }
};
