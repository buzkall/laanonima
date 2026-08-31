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
        Schema::table('books', function(Blueprint $table): void {
            $table->jsonb('author_slugs')->nullable()->after('authors_line');
        });

        /* Same slugs the model writes from now on, for the rows already filed. */
        DB::table('books')->select('id', 'contributors')->orderBy('id')->chunk(200, function($books): void {
            foreach ($books as $book) {
                $contributors = json_decode((string)$book->contributors, true) ?: [];

                $slugs = array_values(array_unique(array_map(
                    fn(array $contributor): string => Str::slug($contributor['name']),
                    array_filter(
                        $contributors,
                        fn(array $contributor): bool => ($contributor['role'] ?? null) === 'autor'
                            && filled($contributor['name'] ?? null),
                    ),
                )));

                DB::table('books')->where('id', $book->id)->update(['author_slugs' => json_encode($slugs)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('books', function(Blueprint $table): void {
            $table->dropColumn('author_slugs');
        });
    }
};
