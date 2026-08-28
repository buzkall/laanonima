<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function(Blueprint $table): void {
            /** Always "#rrggbb": derived from the stored cover, never typed in. */
            $table->char('cover_color', 7)->nullable()->after('cover_source_url');
        });
    }

    public function down(): void
    {
        Schema::table('books', function(Blueprint $table): void {
            $table->dropColumn('cover_color');
        });
    }
};
