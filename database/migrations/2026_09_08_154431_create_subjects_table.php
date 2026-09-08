<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | The subject scheme the shop classifies its whole stock with: THEMA,
         | in Spanish, taken from the shop's own site by `books:import-subjects`
         | and seeded from `database/seeders/data/subjects.json`.
         |
         | The tree is self-referencing, but almost nothing needs to walk it,
         | because a THEMA code *is* its own path: FMM sits under FM sits under
         | F. So "everything filed under fantasy" is `where('code', 'like', 'FM%')`
         | -- left-anchored, which a btree index serves -- and never a recursive
         | query. `parent_id` is there to render the tree, not to search it.
         */
        Schema::create('subjects', function(Blueprint $table): void {
            $table->id();

            /* Short: the longest code in THEMA is a handful of characters, and
               the index on it is what every subtree lookup rides on. */
            $table->string('code', 12)->unique();
            $table->string('name');
            $table->string('slug')->unique();

            $table->foreignId('parent_id')->nullable()->constrained('subjects')->nullOnDelete();

            $table->timestamps();
        });

        Schema::table('books', function(Blueprint $table): void {
            /*
             | One subject, not many.
             |
             | Measured over 5,393 real books off the shop: 68% carry two codes,
             | but 99% of those are one lineage written twice -- FK and FKM, FM
             | and FMM, a code beside its own parent. Only twenty books in five
             | thousand are genuinely about two unrelated things. So the column
             | holds the most specific code and the broader ones follow from it.
             |
             | A `book_subject` pivot can join this later if those twenty ever
             | matter; it would not change what this column means.
             */
            $table->foreignId('subject_id')->nullable()->after('publisher_id')->constrained()->nullOnDelete();

            /*
             | What this replaces: a jsonb array of {scheme, code, heading} that
             | nothing could filter, count or offer as a list to choose from. It
             | is empty on every row, so there is nothing to carry across.
             */
            $table->dropColumn('subjects');
        });
    }

    public function down(): void
    {
        Schema::table('books', function(Blueprint $table): void {
            $table->dropConstrainedForeignId('subject_id');
            $table->jsonb('subjects')->nullable();
        });

        Schema::dropIfExists('subjects');
    }
};
