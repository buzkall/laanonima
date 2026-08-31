<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publishers', function(Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });

        Schema::create('books', function(Blueprint $table): void {
            $table->id();

            $table->char('isbn13', 13)->unique();
            $table->char('isbn10', 10)->nullable();
            $table->char('ean13', 13)->nullable();
            $table->string('slug')->unique();
            $table->string('external_reference')->nullable()->index();

            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('original_title')->nullable();

            $table->jsonb('contributors')->nullable();
            $table->string('authors_line')->nullable()->index();

            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('imprint')->nullable();

            $table->string('collection_name')->nullable();
            $table->string('collection_number')->nullable();

            $table->date('published_on')->nullable();
            $table->smallInteger('published_year')->nullable();
            $table->unsignedSmallInteger('edition_number')->nullable();
            $table->string('edition_statement')->nullable();
            $table->char('country_of_publication', 2)->default('ES');
            $table->string('city_of_publication')->nullable();
            $table->string('legal_deposit')->nullable();

            $table->string('binding')->nullable();
            $table->unsignedSmallInteger('pages')->nullable();
            $table->unsignedSmallInteger('height_mm')->nullable();
            $table->unsignedSmallInteger('width_mm')->nullable();
            $table->unsignedSmallInteger('thickness_mm')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();

            $table->char('language', 3)->default('spa');
            $table->char('original_language', 3)->nullable();

            $table->jsonb('subjects')->nullable();

            $table->text('synopsis')->nullable();
            $table->text('back_cover_text')->nullable();

            $table->string('cover_source_url')->nullable();

            $table->integer('price_cents')->nullable();
            $table->decimal('vat_rate', 5, 2)->default(4);
            $table->char('currency', 3)->default('EUR');
            $table->integer('stock')->default(0);
            $table->string('availability')->default('available');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);

            $table->string('metadata_source')->nullable();
            $table->timestamp('metadata_synced_at')->nullable();
            $table->jsonb('raw_metadata')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'is_featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
        Schema::dropIfExists('publishers');
    }
};
