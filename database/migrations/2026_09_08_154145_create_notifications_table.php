<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function(Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            /*
             | jsonb, not the `text` Laravel's own stub writes.
             |
             | Filament reads its database notifications with
             | `where('data->format', 'filament')`, which Postgres compiles to
             | `data->>'format'` -- an operator that does not exist for text. The
             | panel 500s on every page, and only in production: SQLite runs JSON
             | operators against a text column happily, so the suite stays green
             | and says nothing.
             */
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
