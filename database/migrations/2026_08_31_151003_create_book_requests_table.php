<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_requests', function(Blueprint $table): void {
            $table->id();

            /* Only a signed-in reader can ask us for a book, so who asked is the
               row itself: their name, address and telephone live on `users`. */
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /* Set when the request came off a book page we already catalogue. */
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->string('isbn')->nullable();
            $table->text('notes')->nullable();

            $table->string('status')->default('pending')->index();
            $table->text('admin_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_requests');
    }
};
