<?php

use App\Actions\Cupida\RecommendBook;
use App\Ai\Agents\CupidaAgent;
use App\Filament\Actions\EditCupidaPromptAction;
use App\Filament\Resources\Cupida\CupidaResource;
use App\Filament\Resources\Cupida\Pages\ListCupida;
use App\Livewire\Cupida;
use App\Models\Book;
use App\Models\CupidaRecommendation;
use App\Models\User;
use App\Settings\CupidaSettings;
use App\Support\Cupida\CupidaCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    Storage::fake('public');

    useCupidaFixture();

    $this->actingAs(User::factory()->admin()->create());
});

it('lists what La Cupida has recommended', function(): void {
    $recommendations = CupidaRecommendation::factory()->count(3)->create();

    livewire(ListCupida::class)
        ->assertCanSeeTableRecords($recommendations);
});

it('shows the answers as a bookseller would read them', function(): void {
    CupidaRecommendation::factory()->create([
        'likes'  => ['theme:FM', 'mood:heartbreak', 'book:9788412976137'],
        'passes' => ['theme:DC'],
    ]);

    /* Stored as "theme:FM"; nobody should have to read that. The last round is
       covers, so a session carries EANs too, and an EAN reads no better. */
    livewire(ListCupida::class)
        ->assertSee('Fantasía')
        ->assertSee(__('cupida.moods.heartbreak'))
        ->assertSee('Mientras pasan otras cosas')
        ->assertDontSee('theme:FM')
        ->assertDontSee('book:9788412976137');
});

it('keeps an answer the catalog no longer has, as the key it was stored as', function(): void {
    CupidaRecommendation::factory()->create(['likes' => ['book:9780000000000']]);

    /* A book the shop has stopped stocking is exactly what a bookseller wants
       to see, so it stays on the card rather than resolving to nothing. */
    livewire(ListCupida::class)
        ->assertSee('9780000000000');
});

it('asks for the readers and the books once, not once a card', function(): void {
    CupidaRecommendation::factory()->count(5)->create([
        'user_id' => User::factory(),
        'book_id' => Book::factory(),
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    livewire(ListCupida::class);

    /* Every card names its reader and draws our cover, so without the eager
       load a page of them is three queries a row. */
    $queries = collect(DB::getRawQueryLog())->pluck('raw_query');

    expect($queries->filter(fn(string $query): bool => str_contains($query, 'from "users"')))->toHaveCount(1)
        ->and($queries->filter(fn(string $query): bool => str_contains($query, 'from "books"')))->toHaveCount(1)
        ->and($queries->filter(fn(string $query): bool => str_contains($query, 'from "media"')))->toHaveCount(1);
});

it('draws each card with a cover', function(): void {
    $ours = Book::factory()->create(['isbn13' => '9788412976137']);
    $ours->addCoverFromString(fakeCover());

    $recommendation = CupidaRecommendation::factory()->create([
        'ean'     => '9788412976137',
        'book_id' => $ours->id,
    ]);

    $theirs = CupidaRecommendation::factory()->create([
        'ean'     => '9788433922069',
        'book_id' => null,
    ]);

    /* Ours when we have one, and the shop's resizer -- which answers for any
       EAN it stocks -- when we do not. */
    expect($recommendation->coverUrl())->toBe(url((string)$ours->coverUrl('thumb')))
        ->and($theirs->coverUrl())->toContain('imagen.php?ean=9788433922069');

    livewire(ListCupida::class)
        ->assertSee($theirs->coverUrl());
});

it('filters the three states from the toggle buttons', function(): void {
    $written = CupidaRecommendation::factory()->create(['written' => true]);
    $canned = CupidaRecommendation::factory()->create(['written' => false]);

    /* The buttons post the option keys as strings; blank is the third state. */
    livewire(ListCupida::class)
        ->filterTable('written', '1')
        ->assertCanSeeTableRecords([$written])
        ->assertCanNotSeeTableRecords([$canned])
        ->filterTable('written', '0')
        ->assertCanSeeTableRecords([$canned])
        ->assertCanNotSeeTableRecords([$written])
        ->filterTable('written', '')
        ->assertCanSeeTableRecords([$written, $canned]);
});

it('filters down to the recommendations that are also books on our shelf', function(): void {
    $ours = CupidaRecommendation::factory()->for(Book::factory())->create();
    $theirs = CupidaRecommendation::factory()->create(['book_id' => null]);

    livewire(ListCupida::class)
        ->filterTable('book_id', '1')
        ->assertCanSeeTableRecords([$ours])
        ->assertCanNotSeeTableRecords([$theirs])
        ->filterTable('book_id', '0')
        ->assertCanSeeTableRecords([$theirs])
        ->assertCanNotSeeTableRecords([$ours])
        ->filterTable('book_id', '')
        ->assertCanSeeTableRecords([$ours, $theirs]);
});

it('sorts from the filter panel, not from a select of its own', function(): void {
    $old = CupidaRecommendation::factory()->create([
        'title'      => 'Zorro',
        'created_at' => now()->subWeek(),
    ]);
    $new = CupidaRecommendation::factory()->create([
        'title'      => 'Alba',
        'created_at' => now(),
    ]);

    /* No column is sortable, so Filament renders no sort select above the
       cards; the `sort` filter is the only thing that orders them. Blank is
       the table's own newest-first. */
    livewire(ListCupida::class)
        ->assertDontSee('fi-ta-sorting-settings')
        ->assertCanSeeTableRecords([$new, $old], inOrder: true)
        ->filterTable('sort', 'created_at:asc')
        ->assertCanSeeTableRecords([$old, $new], inOrder: true)
        ->filterTable('sort', 'title:asc')
        ->assertCanSeeTableRecords([$new, $old], inOrder: true)
        ->filterTable('sort', 'title:desc')
        ->assertCanSeeTableRecords([$old, $new], inOrder: true);
});

it('will not order by a column the panel never offered', function(): void {
    $recommendations = CupidaRecommendation::factory()->count(2)->create();

    /* The value comes off the page, so anything that is not one of the three
       columns on the list is dropped rather than handed to `orderBy()`. */
    livewire(ListCupida::class)
        ->filterTable('sort', 'user_id:asc')
        ->assertCanSeeTableRecords($recommendations);
});

it('is called La Cupida, though a row in it is a recommendation', function(): void {
    livewire(ListCupida::class)
        ->assertSee(__('cupida.admin.resource.title'));

    expect(CupidaResource::getPluralModelLabel())->toBe('Recomendaciones');
});

it('sends the EAN to the shop\'s own page for the book', function(): void {
    $recommendation = CupidaRecommendation::factory()->create(['ean' => '9788412976137']);

    /* The address wants the slug as well as the number, and the slug is only
       in the pool. */
    expect($recommendation->shopUrl())
        ->toBe('https://laanonimalibreria.com/libros/9788412976137/mientras-pasan-otras-cosas/');

    livewire(ListCupida::class)
        ->assertSee($recommendation->shopUrl());
});

it('leaves the EAN unlinked when the shop no longer stocks the book', function(): void {
    $dropped = CupidaRecommendation::factory()->create(['ean' => '9780000000000']);

    /* Guessing the address would only send a bookseller to a 404. */
    expect($dropped->shopUrl())->toBeNull();

    livewire(ListCupida::class)
        ->assertSee('9780000000000');
});

it('will not let anyone undo what La Cupida has said', function(): void {
    $recommendation = CupidaRecommendation::factory()->create();
    $bookseller = User::factory()->admin()->create();
    $reader = User::factory()->create();

    /* The log is the only record of what the shop recommended and what it
       spent, so a row cannot be edited or deleted, by anybody. */
    expect($bookseller->can('viewAny', CupidaRecommendation::class))->toBeTrue()
        ->and($bookseller->can('update', $recommendation))->toBeFalse()
        ->and($bookseller->can('delete', $recommendation))->toBeFalse()
        ->and($bookseller->can('deleteAny', CupidaRecommendation::class))->toBeFalse()
        ->and($bookseller->can('create', CupidaRecommendation::class))->toBeFalse()
        ->and($reader->can('viewAny', CupidaRecommendation::class))->toBeFalse();
});

it('has nothing to author', function(): void {
    livewire(ListCupida::class)
        ->assertActionDoesNotExist('create')
        ->assertActionExists(EditCupidaPromptAction::getDefaultName());
});

it('keeps what the shop wants said', function(): void {
    livewire(ListCupida::class)
        ->callAction(EditCupidaPromptAction::getDefaultName(), [
            'extra_instructions' => 'Este mes empujamos a las editoriales gallegas.',
        ])
        ->assertNotified();

    expect(app(CupidaSettings::class)->extra_instructions)->toBe('Este mes empujamos a las editoriales gallegas.');
});

/* The words over the first card are the shop's too, and they are edited from
   the same modal, so a bookseller looking for one finds the other. */
it('keeps what the deck says over the first card', function(): void {
    livewire(ListCupida::class)
        ->callAction(EditCupidaPromptAction::getDefaultName(), [
            'extra_instructions' => null,
            'coach_text'         => "Derecha: sí.\nIzquierda: no.",
        ])
        ->assertNotified();

    expect(app(CupidaSettings::class)->coach_text)->toBe("Derecha: sí.\nIzquierda: no.");
});

it('says the shop\'s piece on top of its own, never instead of it', function(): void {
    $settings = app(CupidaSettings::class);
    $settings->extra_instructions = 'Este mes empujamos a las editoriales gallegas.';
    $settings->save();

    $instructions = (string)new CupidaAgent([])->instructions();

    expect($instructions)
        ->toContain('No inventes títulos')
        ->toContain('Este mes empujamos a las editoriales gallegas.');
});

it('keeps a row for every recommendation it gives', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');

    Ai::fakeAgent(CupidaAgent::class, [[
        'ean'        => '9788412976137',
        'pitch'      => 'Se lee de una sentada.',
        'match_line' => 'Porque dijiste que sí a la poesía.',
    ]]);

    $component = livewire(Cupida::class)->call('start');

    $swiped = [];

    foreach (range(0, 3) as $round) {
        foreach ($component->viewData('cards') as $card) {
            $swiped[] = $card->answer();

            $component->call('swipe', $card->answer(), true);
        }
    }

    $component->call('recommend');

    $kept = CupidaRecommendation::query()->sole();

    /* Against what the component chose, not the fake's EAN: every author card
       is liked here and a liked writer's books are no longer offered, so the
       fake can name a book that was not on the list and the top of it stands
       in. The row has to carry whichever one the reader was given. */
    $chosen = app(CupidaCatalog::class)->book((string)$component->get('chosen'));

    expect($kept)
        ->ean->toBe($chosen['ean'])
        ->title->toBe($chosen['title'])
        ->pitch->toBe('Se lee de una sentada.')
        ->match_line->toBe('Porque dijiste que sí a la poesía.')
        ->written->toBeTrue()
        ->model->toBe(config('cupida.model'))
        ->seed->toBe($component->get('seed'))
        ->and($kept->likes)->toBe($swiped)
        ->and($kept->passes)->toBeEmpty();
});

it('keeps the reader when there is one to keep', function(): void {
    $reader = User::factory()->create();

    $this->actingAs($reader);

    app(RecommendBook::class)(likes: ['theme:DC'], passes: [], write: false, seed: 42);

    expect(CupidaRecommendation::query()->sole())
        ->user_id->toBe($reader->id)
        ->seed->toBe(42)
        ->written->toBeFalse()
        ->model->toBeNull()
        /* Nothing was prompted, so there is nothing to charge for -- and an
           empty column says that, where a zero would say it was free. */
        ->cost->toBeNull()
        ->input_tokens->toBeNull()
        ->output_tokens->toBeNull();
});

it('keeps what a written recommendation cost, worked out from its tokens', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');
    config()->set('cupida.model', 'claude-haiku-4-5');

    Ai::fakeAgent(CupidaAgent::class, [
        new StructuredTextResponse(
            ['ean' => '9788412976137', 'pitch' => 'Se lee de una sentada.', 'match_line' => 'Porque sí.'],
            '',
            new Usage(promptTokens: 4_000, completionTokens: 200),
            new Meta('anthropic', 'claude-haiku-4-5'),
        ),
    ]);

    app(RecommendBook::class)(likes: ['theme:DC'], passes: []);

    /* 4,000 in at $1/M and 200 out at $5/M. */
    expect(CupidaRecommendation::query()->sole())
        ->input_tokens->toBe(4_000)
        ->output_tokens->toBe(200)
        ->and((float)CupidaRecommendation::query()->sole()->cost)->toBe(0.005);
});

it('prices the dated model an alias resolved to', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');
    config()->set('cupida.model', 'claude-haiku-4-5');

    /* What is asked for is an alias; what answers is the version behind it.
       `cupida.prices` names aliases, so a rate list that has not been touched
       since a new release still prices the call. */
    Ai::fakeAgent(CupidaAgent::class, [
        new StructuredTextResponse(
            ['ean' => '9788412976137', 'pitch' => 'Se lee de una sentada.', 'match_line' => 'Porque sí.'],
            '',
            new Usage(promptTokens: 4_000, completionTokens: 200),
            new Meta('anthropic', 'claude-haiku-4-5-20251001'),
        ),
    ]);

    app(RecommendBook::class)(likes: ['theme:DC'], passes: []);

    $kept = CupidaRecommendation::query()->sole();

    expect($kept->model)->toBe('claude-haiku-4-5-20251001')
        ->and((float)$kept->cost)->toBe(0.005);
});

it('names and prices the model that answered, not the one that was asked for', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');
    config()->set('cupida.model', 'claude-haiku-4-5');

    /* The config asked for haiku and something else answered. What goes on the
       row is what answered -- and it has no rate on file, so the tokens are
       kept and the cost is left empty rather than priced as haiku. */
    Ai::fakeAgent(CupidaAgent::class, [
        new StructuredTextResponse(
            ['ean' => '9788412976137', 'pitch' => 'Se lee de una sentada.', 'match_line' => 'Porque sí.'],
            '',
            new Usage(promptTokens: 4_000, completionTokens: 200),
            new Meta('anthropic', 'claude-something-unpriced'),
        ),
    ]);

    app(RecommendBook::class)(likes: ['theme:DC'], passes: []);

    expect(CupidaRecommendation::query()->sole())
        ->model->toBe('claude-something-unpriced')
        ->input_tokens->toBe(4_000)
        ->output_tokens->toBe(200)
        ->cost->toBeNull();
});

it('still hands over a book when the shop has run out of credit', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');

    /* What the SDK raises on Anthropic's "your credit balance is too low": a
       402, or a 400 whose message matches one of the gateway's billing
       patterns. Either way it arrives here as an exception, which is the whole
       reason the fallback is a `catch (Throwable)` rather than a check. */
    Ai::fakeAgent(CupidaAgent::class, [
        fn() => throw InsufficientCreditsException::forProvider('anthropic', 402),
    ]);

    $recommendation = app(RecommendBook::class)(likes: ['theme:DC'], passes: []);

    expect($recommendation)->not->toBeNull()
        ->and($recommendation->written)->toBeFalse()
        ->and($recommendation->pitch)->toBe(__('cupida.result.fallback_pitch'))
        ->and(CupidaRecommendation::query()->sole())->written->toBeFalse()->model->toBeNull()->cost->toBeNull();
});

it('keeps what the scoring would have said, beside what the model said', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');

    /* The model is told to take a book that is not the top of the shortlist,
       which is the only case worth recording: the point of the column is to ask
       whether it is doing anything the scoring was not already doing. */
    Ai::fakeAgent(CupidaAgent::class, [[
        'ean'        => '9788433922069',
        'pitch'      => 'Se lee de una sentada.',
        'match_line' => 'Porque sí.',
    ]]);

    app(RecommendBook::class)(likes: ['theme:DC'], passes: []);

    $kept = CupidaRecommendation::query()->sole();

    expect($kept->ean)->toBe('9788433922069')
        ->and($kept->shortlist_ean)->not->toBe($kept->ean)
        ->and($kept->shortlist_title)->not->toBeNull()
        ->and($kept->shortlist_rank)->toBeGreaterThan(1)
        /* And the card says so in words rather than in a position. */
        ->and($kept->shortlistPick())->toContain((string)$kept->shortlist_title);
});

it('does not count a canned line as the model agreeing', function(): void {
    /* No key, so nothing was prompted: the fallback *is* the top of the
       shortlist and agrees by construction. Counting that would flatter the
       model with every failure. */
    config()->set('ai.providers.anthropic.key');

    app(RecommendBook::class)(likes: ['theme:DC'], passes: []);

    $kept = CupidaRecommendation::query()->sole();

    expect($kept->shortlist_rank)->toBe(1)
        ->and($kept->shortlist_ean)->toBe($kept->ean)
        /* Still says something: the question "what would this reader get with
           no model" has an answer here too, and a blank would have to be read
           as agreement. */
        ->and($kept->shortlistPick())->toBe(__('cupida.admin.shortlist.same'));
});

it('can be filtered down to the sessions where the model overruled the list', function(): void {
    $overruled = CupidaRecommendation::factory()->create(['shortlist_rank' => 7]);
    $agreed = CupidaRecommendation::factory()->create(['shortlist_rank' => 1]);
    $canned = CupidaRecommendation::factory()->fallback()->create();

    livewire(ListCupida::class)
        ->filterTable('agreed', true)
        ->assertCanSeeTableRecords([$agreed])
        ->assertCanNotSeeTableRecords([$overruled, $canned])
        ->filterTable('agreed', false)
        ->assertCanSeeTableRecords([$overruled])
        ->assertCanNotSeeTableRecords([$agreed, $canned]);
});

it('adds up what the shop has spent', function(): void {
    CupidaRecommendation::factory()->count(3)->create(['cost' => 0.004]);

    livewire(ListCupida::class)
        ->assertCanSeeTableRecords(CupidaRecommendation::all())
        ->assertSee('0,0120');
});

it('says why a card has no price rather than leaving the slot empty', function(): void {
    CupidaRecommendation::factory()->fallback()->create();
    CupidaRecommendation::factory()->create([
        'model'         => 'claude-something-unpriced',
        'input_tokens'  => 4_000,
        'output_tokens' => 200,
        'cost'          => null,
    ]);

    /* A canned line cost nothing; a written one with no price means the rates
       have gone stale, and those are not the same thing to a bookseller. */
    livewire(ListCupida::class)
        ->assertSee(__('cupida.admin.cost_none'))
        ->assertSee(__('cupida.admin.cost_unknown'));
});
