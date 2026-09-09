<?php

use App\Ai\Agents\CupidaAgent;
use App\Livewire\Cupida;
use Laravel\Ai\Ai;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    useCupidaFixture();
});

/**
 * A component past the opening card, ready to be swiped.
 */
function cupidaDeck(): object
{
    return livewire(Cupida::class)->call('start');
}

/**
 * Answer every card of the round the component is on.
 *
 * The deck is rebuilt from its seed on each render, so the cards have to be
 * read back rather than guessed -- which is also how the page itself works.
 */
function swipeThroughRound(object $component, bool $liked = true): object
{
    foreach ($component->viewData('cards') as $card) {
        $component->call('swipe', $card->answer(), $liked);
    }

    return $component;
}

it('asks three questions and then goes off to think', function(): void {
    $component = cupidaDeck();

    foreach (range(0, 2) as $round) {
        expect($component->get('round'))->toBe($round);

        swipeThroughRound($component);
    }

    /* The wait is the one screen with nothing on it, so the mark stands over
       it -- floating, and still on screen for a reader who has turned motion
       off. */
    $component->assertSet('thinking', true)
        ->assertSee(__('cupida.thinking.heading'))
        ->assertSee('cupida-float')
        ->assertSee('la-cupida');
});

it('writes the recommendation with the model', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');

    Ai::fakeAgent(CupidaAgent::class, [[
        'ean'        => '9788412976137',
        'pitch'      => 'Se lee de una sentada y se queda mucho más tiempo.',
        'match_line' => 'Porque dijiste que sí a la poesía.',
    ]]);

    $component = cupidaDeck();

    foreach (range(0, 2) as $round) {
        swipeThroughRound($component);
    }

    $component->call('recommend')
        ->assertSet('chosen', '9788412976137')
        ->assertSet('written', true)
        ->assertSee('Mientras pasan otras cosas')
        ->assertSee('Se lee de una sentada y se queda mucho más tiempo.')
        ->assertSee('Porque dijiste que sí a la poesía.');

    Ai::assertAgentWasPromptedTimes(CupidaAgent::class, 1);
});

it('never prompts twice for one session', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');

    Ai::fakeAgent(CupidaAgent::class, [[
        'ean'        => '9788412976137',
        'pitch'      => 'Una vez y no más.',
        'match_line' => 'Porque sí.',
    ]]);

    $component = cupidaDeck();

    foreach (range(0, 2) as $round) {
        swipeThroughRound($component);
    }

    $component->call('recommend')
        ->call('recommend')
        ->call('recommend');

    Ai::assertAgentWasPromptedTimes(CupidaAgent::class, 1);
});

it('still recommends a book with no key configured', function(): void {
    config(['ai.providers.anthropic.key' => null]);

    Ai::fakeAgent(CupidaAgent::class, [['ean' => '9788412976137', 'pitch' => 'x', 'match_line' => 'y']]);

    $component = cupidaDeck();

    foreach (range(0, 2) as $round) {
        swipeThroughRound($component);
    }

    $component->call('recommend')
        ->assertSet('written', false)
        ->assertSee(__('cupida.result.fallback_pitch'));

    Ai::assertAgentNeverPrompted(CupidaAgent::class);
});

it('stops paying for a pitch once the address has had its share', function(): void {
    config()->set('ai.providers.anthropic.key', 'test-key');
    config()->set('cupida.rate_limit.attempts', 0);

    Ai::fakeAgent(CupidaAgent::class, [['ean' => '9788412976137', 'pitch' => 'x', 'match_line' => 'y']]);

    $component = cupidaDeck();

    foreach (range(0, 2) as $round) {
        swipeThroughRound($component);
    }

    $component->call('recommend')
        ->assertSet('written', false)
        ->assertSee(__('cupida.result.fallback_pitch'));

    Ai::assertAgentNeverPrompted(CupidaAgent::class);
});

it('ignores a card that was never dealt', function(): void {
    cupidaDeck()
        ->call('swipe', 'theme:INVENTADO', true)
        ->assertSet('round', 0)
        ->assertSet('likes', []);
});

it('counts a card once however many times it is answered', function(): void {
    $component = cupidaDeck();

    $first = $component->viewData('cards')[0];

    $component->call('swipe', $first->answer(), true)
        ->call('swipe', $first->answer(), false)
        ->assertSet('likes', [$first->answer()])
        ->assertSet('passes', []);
});

it('deals again from nothing when a reader starts over', function(): void {
    config(['ai.providers.anthropic.key' => null]);

    $component = cupidaDeck();

    foreach (range(0, 2) as $round) {
        swipeThroughRound($component);
    }

    $component->call('recommend')->call('restart')
        ->assertSet('round', 0)
        ->assertSet('likes', [])
        ->assertSet('passes', [])
        ->assertSet('chosen', null)
        ->assertSee(__('cupida.questions.theme'));
});
