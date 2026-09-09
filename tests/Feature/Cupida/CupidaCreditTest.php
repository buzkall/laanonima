<?php

use App\Actions\Cupida\RecommendBook;
use App\Ai\Agents\CupidaAgent;
use App\Filament\Actions\EditCupidaCreditAction;
use App\Filament\Resources\Cupida\Pages\ListCupida;
use App\Models\CupidaRecommendation;
use App\Models\User;
use App\Notifications\CupidaCreditExhausted;
use App\Notifications\CupidaCreditRunningLow;
use App\Settings\CupidaSettings;
use App\Support\Cupida\CupidaBudget;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    useCupidaFixture();

    Notification::fake();

    $this->admin = User::factory()->admin()->create();
});

/**
 * What is left on the account, the way the app works it out.
 */
function cupidaBudget(): CupidaBudget
{
    return new CupidaBudget(app(CupidaSettings::class));
}

/**
 * Puts a balance on the account, the way the panel would.
 */
function cupidaToppedUp(?float $balance, ?DateTimeInterface $on = null): void
{
    $settings = app(CupidaSettings::class);

    $settings->credit_balance = $balance;
    $settings->credit_topped_up_at = $on;
    $settings->save();
}

/**
 * A written recommendation whose usage is ours to choose, so a test can spend
 * an exact number of dollars.
 */
function cupidaSpends(float $dollars): void
{
    config()->set('ai.providers.anthropic.key', 'test-key');
    config()->set('cupida.model', 'test-model');
    config()->set('cupida.prices.test-model', [
        'input'       => 0,
        'output'      => 1_000_000 * $dollars,
        'cache_write' => 0,
        'cache_read'  => 0,
    ]);

    Ai::fakeAgent(CupidaAgent::class, [
        new StructuredTextResponse(
            ['ean' => '9788412976137', 'pitch' => 'Se lee de una sentada.', 'match_line' => 'Porque sí.'],
            '',
            new Usage(completionTokens: 1),
            new Meta('anthropic', 'test-model'),
        ),
    ]);

    app(RecommendBook::class)(likes: ['theme:DC'], passes: []);
}

it('hands back the top-up date as a date', function(): void {
    cupidaToppedUp(10.00, now()->subDay());

    /* Guarding a trap rather than a feature. spatie/laravel-settings resolves a
       property's type from its docblock the moment it has one, and falls back
       to the native type only when there is no doc comment at all -- so a
       `/**` with prose and no `@var` silently builds no cast, and the raw
       string out of the JSON column is assigned to a typed property. Writing
       `@var` instead lasts until Pint deletes it as superfluous. Either way it
       fails on read, far from the change that caused it. */
    expect(app(CupidaSettings::class)->credit_topped_up_at)
        ->toBeInstanceOf(CarbonImmutable::class);
});

it('counts what is left down from what the shop put on', function(): void {
    cupidaToppedUp(10.00, now()->subDay());

    cupidaSpends(2.50);

    expect(cupidaBudget()->spent())->toBe(2.5)
        ->and(cupidaBudget()->remaining())->toBe(7.5)
        ->and(cupidaBudget()->runningLow())->toBeFalse();
});

it('leaves out what was spent before the top-up', function(): void {
    /* Paid for by the previous balance: counting it here would take the same
       dollar off twice. */
    CupidaRecommendation::factory()->create([
        'cost'       => 4.00,
        'created_at' => now()->subWeek(),
    ]);

    cupidaToppedUp(10.00, now()->subDay());

    expect(cupidaBudget()->spent())->toBe(0.0)
        ->and(cupidaBudget()->remaining())->toBe(10.0);
});

it('watches nothing until somebody says what was put on', function(): void {
    CupidaRecommendation::factory()->create(['cost' => 99.00]);

    /* No balance is not a balance of zero: a shop that has not told us what it
       topped up has not asked to be warned. */
    expect(cupidaBudget()->remaining())->toBeNull()
        ->and(cupidaBudget()->runningLow())->toBeFalse();

    Notification::assertNothingSent();
});

it('counts nothing against a balance with no date beside it', function(): void {
    CupidaRecommendation::factory()->create(['cost' => 4.00, 'created_at' => now()->subYear()]);

    /* Not reachable from the modal any more -- it stamps a date whenever a
       balance is saved -- but the two are separate columns and a balance
       written any other way lands here. Summing the whole table against it
       charged this balance for every dollar La Cupida ever spent, so a shop
       that had just topped up was told it was nearly out. */
    cupidaToppedUp(10.00);

    expect(cupidaBudget()->spent())->toBe(0.0)
        ->and(cupidaBudget()->remaining())->toBe(10.0)
        ->and(cupidaBudget()->runningLow())->toBeFalse();
});

it('dates a top-up saved without one', function(): void {
    $this->actingAs($this->admin);

    cupidaSpends(0.50);

    livewire(ListCupida::class)
        ->callAction(EditCupidaCreditAction::getDefaultName(), [
            'credit_balance'      => 20.00,
            'credit_topped_up_at' => null,
        ])
        ->assertNotified();

    /* The balance and its date are one fact, and the countdown cannot use the
       first without the second. Blank means today rather than never. */
    expect(app(CupidaSettings::class)->credit_topped_up_at)
        ->toBeInstanceOf(CarbonImmutable::class)
        ->and(app(CupidaSettings::class)->credit_topped_up_at->toDateString())
        ->toBe(now()->toDateString())
        ->and(cupidaBudget()->remaining())->toBe(19.5);
});

it('writes a balance already spent past as a debt and not as a price', function(): void {
    cupidaToppedUp(1.00, now()->subDay());

    cupidaSpends(4.50);

    /* Nothing blocks a prompt, so going under is ordinary. "$-3.50" reads as a
       price with a stray sign in it. */
    expect(cupidaBudget()->remaining())->toBe(-3.5)
        ->and(cupidaBudget()->label())->toBe('-$3.50')
        ->and(CupidaBudget::money(3.5))->toBe('$3.50');
});

it('warns the shop once the account is nearly empty', function(): void {
    cupidaToppedUp(1.20, now()->subDay());

    cupidaSpends(0.50);

    Notification::assertSentTo($this->admin, CupidaCreditRunningLow::class);
});

it('does not warn twice in the same afternoon', function(): void {
    cupidaToppedUp(1.20, now()->subDay());

    cupidaSpends(0.50);
    cupidaSpends(0.10);

    /* The trigger is a reader swiping, so without the throttle this is one mail
       per swipe for as long as the balance stays low. */
    Notification::assertSentToTimes($this->admin, CupidaCreditRunningLow::class, 1);
});

it('says nothing while there is money on the account', function(): void {
    cupidaToppedUp(50.00, now()->subDay());

    cupidaSpends(0.50);

    Notification::assertNothingSent();
});

it('reports the provider actually refusing for credit', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');

    Ai::fakeAgent(CupidaAgent::class, [
        fn() => throw InsufficientCreditsException::forProvider('anthropic', 402),
    ]);

    $recommendation = app(RecommendBook::class)(likes: ['theme:DC'], passes: []);

    /* The reader is not the one who pays for this: they still get a book, from
       the scoring, with the canned line. */
    expect($recommendation->written)->toBeFalse();

    Notification::assertSentTo($this->admin, CupidaCreditExhausted::class);
});

it('hands both warnings to a worker rather than to the reader who triggered them', function(): void {
    /* Both are raised inside a swipe, so neither mail may put an unreachable
       SMTP host in front of that reader's book. It does mean the site needs a
       queue worker: without one the jobs wait in `jobs` and nobody is told at
       all. */
    expect(new CupidaCreditExhausted)->toBeInstanceOf(ShouldQueue::class)
        ->and(new CupidaCreditRunningLow(1.0))->toBeInstanceOf(ShouldQueue::class);
});

it('keeps quiet about the provider failing for any other reason', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');

    Ai::fakeAgent(CupidaAgent::class, [
        fn() => throw new RuntimeException('The provider had a bad day.'),
    ]);

    app(RecommendBook::class)(likes: ['theme:DC'], passes: []);

    /* Nothing the shop can act on, so nothing lands in their inbox. */
    Notification::assertNothingSent();
});

it('mails whoever looks after the site, and rings the bell for whoever is in the panel', function(): void {
    config()->set('site.admin_email', 'quien-lleva-la-web@example.com');

    cupidaToppedUp(1.20, now()->subDay());

    cupidaSpends(0.50);

    /* The bell belongs to whoever is signed in, so it goes to the admin
       users -- and only the bell, or somebody who is both would be told
       twice. */
    Notification::assertSentTo($this->admin, CupidaCreditRunningLow::class, fn($notification, array $channels): bool => $channels === ['database']);

    Notification::assertSentTo(
        new AnonymousNotifiable()->route('mail', 'quien-lleva-la-web@example.com'),
        CupidaCreditRunningLow::class,
    );
});

it('falls back to the shop when the environment names nobody', function(): void {
    config()->set('site.admin_email');
    config()->set('site.contact_email', 'hola@laanonimalibreria.com');

    cupidaToppedUp(1.20, now()->subDay());

    cupidaSpends(0.50);

    /* An unset address that silently warns nobody is worse than one that warns
       the wrong inbox: only one of the two gets noticed. */
    Notification::assertSentTo(
        new AnonymousNotifiable()->route('mail', 'hola@laanonimalibreria.com'),
        CupidaCreditRunningLow::class,
    );
});

it('lets a bookseller write down a top-up, and starts warning again after one', function(): void {
    $this->actingAs($this->admin);

    cupidaToppedUp(1.00, now()->subWeek());

    cupidaSpends(0.50);

    Notification::assertSentToTimes($this->admin, CupidaCreditRunningLow::class, 1);

    livewire(ListCupida::class)
        ->callAction(EditCupidaCreditAction::getDefaultName(), [
            'credit_balance'      => 40.00,
            'credit_topped_up_at' => now()->toDateString(),
        ])
        ->assertNotified();

    /* 40 less the 50 cents spent earlier today: the date picker records a day,
       so it starts counting from midnight and the morning's spend is charged to
       the balance entered this afternoon. Wrong by half a dollar in the
       cautious direction -- it warns early rather than late -- which is the
       side of a credit warning worth being wrong on. */
    expect(app(CupidaSettings::class)->credit_balance)->toBe(40.0)
        ->and(cupidaBudget()->remaining())->toBe(39.5);

    /* The throttle was cleared with the top-up, so the next time it runs down
       the shop hears about it rather than waiting out a window that started on
       an account since refilled. */
    cupidaToppedUp(1.00, app(CupidaSettings::class)->credit_topped_up_at);

    cupidaSpends(0.50);

    Notification::assertSentToTimes($this->admin, CupidaCreditRunningLow::class, 2);
});
