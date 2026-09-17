<?php

use App\Models\Book;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function(Blueprint $table): void {
            $table->text('search_text')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('create extension if not exists pg_trgm');
            DB::statement('create index books_search_text_trgm on books using gin (search_text gin_trgm_ops)');
        }

        Book::query()->chunkById(200, function($books): void {
            $books->each(fn(Book $book) => $book->syncSearchText());
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop index if exists books_search_text_trgm');
        }

        Schema::table('books', function(Blueprint $table): void {
            $table->dropColumn('search_text');
        });
    }
};
