<?php

use App\Filament\Resources\Cupida\Pages\ListCupida;
use App\Models\Book;
use App\Models\CupidaRecommendation;
use App\Models\User;
use App\Settings\CupidaSettings;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    useCupidaFixture();

    $this->actingAs(User::factory()->admin()->create());
});

/**
 * The file the browser was handed, decoded.
 *
 * A streamed download reaches Livewire as base64 in the effects rather than as
 * a response body, the same way QrCodeGeneratorTest reads its QR.
 *
 * @return array<string, mixed>
 */
function exportedLog(Testable $component): array
{
    $json = base64_decode(data_get($component->effects, 'download.content'));

    return json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
}

it('downloads the log as JSON', function(): void {
    CupidaRecommendation::factory()->count(3)->create();

    $component = livewire(ListCupida::class)->callAction('exportCupidaLog');

    expect(data_get($component->effects, 'download.name'))->toEndWith('.json')
        ->and(exportedLog($component)['sessions'])->toHaveCount(3);
});

it('carries what a session needs to be judged', function(): void {
    $book = Book::factory()->create();

    CupidaRecommendation::factory()->create([
        'book_id'        => $book->id,
        'likes'          => ['theme:FM'],
        'passes'         => ['theme:DC'],
        'pitch'          => 'Te va a gustar.',
        'cost'           => 0.005100,
        'shortlist_rank' => 4,
    ]);

    $session = exportedLog(livewire(ListCupida::class)->callAction('exportCupidaLog'))['sessions'][0];

    /* Labels and not "theme:FM": whatever reads this has to know a key is a
       genre without being handed the catalog too. */
    expect($session['likes'])->toBe(['Fantasía'])
        ->and($session['passes'])->toBe(['Poesía'])
        ->and($session['pitch'])->toBe('Te va a gustar.')
        ->and($session['recommended']['in_catalog'])->toBeTrue()
        ->and($session['cost_usd'])->toBe(0.0051)
        /* The control the pitch is judged against: the model went four deep
           into the list rather than taking the book the scoring had chosen. */
        ->and($session['shortlist']['rank_of_pick'])->toBe(4)
        ->and($session['shortlist']['agreed'])->toBeFalse();
});

it('leaves agreement unanswered for a session nothing was written for', function(): void {
    CupidaRecommendation::factory()->fallback()->create();

    $session = exportedLog(livewire(ListCupida::class)->callAction('exportCupidaLog'))['sessions'][0];

    /* A canned line is the top of the shortlist by construction, so false would
       be a lie and true would credit the model with a failure. */
    expect($session['written'])->toBeFalse()
        ->and($session['shortlist']['agreed'])->toBeNull();
});

it('totals the rows it exported', function(): void {
    CupidaRecommendation::factory()->count(2)->create(['ean' => '9788412976137', 'cost' => 0.01]);
    CupidaRecommendation::factory()->fallback()->create(['ean' => '9788412976137']);

    $summary = exportedLog(livewire(ListCupida::class)->callAction('exportCupidaLog'))['summary'];

    expect($summary['sessions'])->toBe(3)
        ->and($summary['written'])->toBe(2)
        ->and($summary['fallback'])->toBe(1)
        ->and($summary['overruled_shortlist'])->toBe(2)
        ->and($summary['agreed_with_shortlist'])->toBe(0)
        /* Three sessions and one book between them, which is the failure a
           page of well-written pitches hides. */
        ->and($summary['distinct_books'])->toBe(1)
        ->and($summary['cost_usd'])->toBe(0.02);
});

it('exports what the filters left on screen and totals only that', function(): void {
    CupidaRecommendation::factory()->count(2)->create();
    CupidaRecommendation::factory()->fallback()->create();

    $component = livewire(ListCupida::class)
        ->filterTable('written', true)
        ->callAction('exportCupidaLog');

    $log = exportedLog($component);

    expect($log['sessions'])->toHaveCount(2)
        ->and($log['summary']['sessions'])->toBe(2)
        ->and($log['summary']['fallback'])->toBe(0);
});

it('sends the prompt in effect along with the pitches', function(): void {
    CupidaRecommendation::factory()->create();

    $settings = app(CupidaSettings::class);
    $settings->extra_instructions = 'Este mes empujamos editoriales gallegas.';
    $settings->save();

    $prompt = exportedLog(livewire(ListCupida::class)->callAction('exportCupidaLog'))['prompt'];

    /* A pitch cannot be judged against nothing, and which half of the brief a
       complaint belongs to is the difference between a deploy and a modal. */
    expect($prompt['base'])->toContain('Eres la librera de La Anónima')
        ->and($prompt['extra'])->toBe('Este mes empujamos editoriales gallegas.');
});
