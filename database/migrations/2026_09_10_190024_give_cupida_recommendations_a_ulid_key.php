<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 | A recommendation is now addressable from outside, because a reader who has
 | just been handed a book wants to send somebody what La Cupida said about it
 | -- and a serial key would have made /la-cupida/recomendacion/1, /2, /3 a walk
 | through every other reader's session. The row keeps no address and no
 | fingerprint, but it does keep user_id, likes and passes, and a guessable URL
 | is what turns an answer nobody can find into one anybody can.
 |
 | The key is replaced rather than joined by a second public column: nothing in
 | the schema points at cupida_recommendations.id -- both of its foreign keys
 | point outwards, at users and books -- so there is nothing for a second column
 | to protect, and one identifier is easier to reason about than two.
 |
 | The table is rebuilt rather than altered in place because changing a primary
 | key's type is where Postgres and SQLite stop agreeing, and this has to run
 | the same in both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupida_recommendations_ulid', function(Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('seed');
            $table->jsonb('likes');
            $table->jsonb('passes');
            $table->string('ean', 13);
            $table->string('title');
            $table->string('author')->nullable();
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->text('pitch');
            $table->text('match_line')->nullable();
            $table->string('shortlist_ean', 13)->nullable();
            $table->string('shortlist_title')->nullable();
            $table->string('shortlist_author')->nullable();
            $table->unsignedSmallInteger('shortlist_rank')->nullable();
            $table->boolean('written')->default(false);
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        /* Seeded from the row's own timestamp rather than from now: a ULID
           sorts by the time it was minted, so minting them in a backfill would
           file every old row under this afternoon and lose the order the table
           already had. */
        $this->copyRows('cupida_recommendations', 'cupida_recommendations_ulid', fn(stdClass $row): string => (string)Str::ulid(
            $row->created_at === null ? null : new DateTimeImmutable((string)$row->created_at),
        ));

        Schema::drop('cupida_recommendations');
        Schema::rename('cupida_recommendations_ulid', 'cupida_recommendations');
    }

    public function down(): void
    {
        Schema::create('cupida_recommendations_serial', function(Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('seed');
            $table->jsonb('likes');
            $table->jsonb('passes');
            $table->string('ean', 13);
            $table->string('title');
            $table->string('author')->nullable();
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->text('pitch');
            $table->text('match_line')->nullable();
            $table->string('shortlist_ean', 13)->nullable();
            $table->string('shortlist_title')->nullable();
            $table->string('shortlist_author')->nullable();
            $table->unsignedSmallInteger('shortlist_rank')->nullable();
            $table->boolean('written')->default(false);
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        /* The ULIDs are gone either way, so the rows are renumbered from one in
           the order they were written. */
        $this->copyRows('cupida_recommendations', 'cupida_recommendations_serial', null);

        Schema::drop('cupida_recommendations');
        Schema::rename('cupida_recommendations_serial', 'cupida_recommendations');
    }

    private function copyRows(string $from, string $to, ?Closure $key): void
    {
        DB::table($from)->orderBy('created_at')->orderBy('id')->chunk(200, function($rows) use ($to, $key): void {
            $carried = [];

            foreach ($rows as $row) {
                $values = (array)$row;

                if ($key instanceof Closure) {
                    $values['id'] = $key($row);
                } else {
                    unset($values['id']);
                }

                $carried[] = $values;
            }

            DB::table($to)->insert($carried);
        });
    }
};
