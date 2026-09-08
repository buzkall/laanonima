<?php

use App\Actions\Cupida\RecommendBook;
use App\Ai\Agents\CupidaAgent;
use App\Filament\Actions\EditCupidaPromptAction;
use App\Filament\Resources\Cupida\CupidaResource;
use App\Filament\Resources\Cupida\Pages\ListCupida;
use App\Livewire\Cupida;
use App\Models\CupidaPrompt;
use App\Models\CupidaRecommendation;
use App\Models\User;
use Laravel\Ai\Ai;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
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
        'likes'  => ['theme:FM', 'mood:heartbreak'],
        'passes' => ['theme:DC'],
    ]);

    /* Stored as "theme:FM"; nobody should have to read that. */
    livewire(ListCupida::class)
        ->assertSee('Fantasía')
        ->assertSee(__('cupida.moods.heartbreak'))
        ->assertDontSee('theme:FM');
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

it('is called La Cupida, though a row in it is a recommendation', function(): void {
    livewire(ListCupida::class)
        ->assertSee(__('cupida.admin.resource.title'));

    expect(CupidaResource::getPluralModelLabel())->toBe('Recomendaciones');
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

    expect(CupidaPrompt::extra())->toBe('Este mes empujamos a las editoriales gallegas.');
});

it('says the shop\'s piece on top of its own, never instead of it', function(): void {
    CupidaPrompt::current()->update([
        'extra_instructions' => 'Este mes empujamos a las editoriales gallegas.',
    ]);

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

    foreach (range(0, 3) as $round) {
        foreach ($component->viewData('cards') as $card) {
            $component->call('swipe', $card->answer(), true);
        }
    }

    $component->call('recommend');

    $kept = CupidaRecommendation::query()->sole();

    expect($kept)
        ->ean->toBe('9788412976137')
        ->title->toBe('Mientras pasan otras cosas')
        ->pitch->toBe('Se lee de una sentada.')
        ->match_line->toBe('Porque dijiste que sí a la poesía.')
        ->written->toBeTrue()
        ->model->toBe(config('cupida.model'))
        ->seed->toBe($component->get('seed'))
        ->and($kept->likes)->toHaveCount(24)
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

it('keeps the tokens but no price for a model with no rate on file', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');
    config()->set('cupida.model', 'claude-something-unpriced');

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
        ->input_tokens->toBe(4_000)
        ->output_tokens->toBe(200)
        ->cost->toBeNull();
});

it('adds up what the shop has spent', function(): void {
    CupidaRecommendation::factory()->count(3)->create(['cost' => 0.004]);

    livewire(ListCupida::class)
        ->assertCanSeeTableRecords(CupidaRecommendation::all())
        ->assertSee('0,0120');
});
