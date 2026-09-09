<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupida_recommendations', function(Blueprint $table): void {
            $table->id();

            /* La Cupida is open to anyone: most rows have no reader behind them,
               and that is the ordinary case rather than a gap. Nothing else
               identifying is kept -- no address, no fingerprint -- so a row is a
               record of what was asked and answered, not of who asked it. */
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            /* Puts the exact three rounds back, the way `data-seed` does for the
               shelf: without it the answers below are keys with no cards. */
            $table->unsignedBigInteger('seed');

            /* Answers as "kind:key" -- "theme:FM", "author:le-guin-ursula-k",
               "mood:heartbreak". Kept as swiped rather than as labels, because a
               label is the catalog's to change and a code is not. */
            $table->jsonb('likes');
            $table->jsonb('passes');

            /* The book, copied rather than referenced: it comes out of the
               scraped pool, which is rebuilt by hand every few months, so an
               EAN that resolves today may not resolve after the next scrape.
               What was recommended has to stay readable either way. */
            $table->string('ean', 13);
            $table->string('title');
            $table->string('author')->nullable();

            /* Set only when the shop stocks it here too. */
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();

            $table->text('pitch');
            $table->text('match_line')->nullable();

            /* What the scoring would have handed over on its own, kept beside
               what the model actually chose. Free to record -- the shortlist is
               already built before anything is prompted -- and it is the only
               way to answer whether the model is earning its keep: `rank` is
               where in the thirty its pick sat, so a column of ones means the
               scoring was already doing the work.

               On the fallback path the two are the same book and the rank is
               one, which is not agreement; `written` is what tells them apart. */
            $table->string('shortlist_ean', 13)->nullable();
            $table->string('shortlist_title')->nullable();
            $table->string('shortlist_author')->nullable();
            $table->unsignedSmallInteger('shortlist_rank')->nullable();

            /* False when the pitch is the canned line: no key configured, the
               provider failed, or the address had used up its share. Worth
               keeping -- a week of rows that are all false is the demo quietly
               running without a model rather than a page that looks fine. */
            $table->boolean('written')->default(false);
            $table->string('model')->nullable();

            /* What the pitch cost, in dollars, and the tokens it is worked out
               from. The provider prices nothing for us -- it answers with token
               counts -- so the figure is those counts times `cupida.prices`,
               and it is only as right as that list. Null rather than zero when
               nothing was written or the model has no rate on file: an empty
               column says we do not know, a zero would say it was free. */
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost', 10, 6)->nullable();

            $table->timestamps();

            $table->index('created_at');
        });

        /*
         | Everything a bookseller edits -- what the shop wants said, what it
         | put on the Anthropic account, where the warnings go -- is in
         | App\Settings\CupidaSettings, not here. Each of those is one value
         | with no rows and no history, and a table apiece would be a model, a
         | migration and a `firstOrCreate()` standing in for a property.
         */
    }

    public function down(): void
    {
        Schema::dropIfExists('cupida_recommendations');
    }
};
