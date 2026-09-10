<?php

use App\Ai\Agents\CupidaAgent;
use App\Livewire\Cupida;
use App\Support\Cupida\CupidaCatalog;
use Laravel\Ai\Ai;
use Laravel\Ai\Prompts\AgentPrompt;

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

    /* Not the fake's EAN: every author card in the fixture is liked here,
       and a liked writer's books are no longer offered, so the answer names
       a book that was not on the list and the top of it stands in. Which book
       the model is allowed to name is `CupidaRecommendationTest`'s. */
    $component->call('recommend')
        ->assertSet('chosen', fn(?string $ean): bool => $ean !== null)
        ->assertSet('written', true)
        ->assertSee('Se lee de una sentada y se queda mucho más tiempo.')
        ->assertSee('Porque dijiste que sí a la poesía.');

    Ai::assertAgentWasPromptedTimes(CupidaAgent::class, 1);
});

it('keeps the word the opening card promised, in the prompt and over the book', function(): void {
    /* One seed, one story: the opening card promises "tu próximo flechazo",
       the model is told so, and the heading over the book reads "Tu
       flechazo" -- not a fixed "Tu cita" whatever was promised. */
    config()->set('ai.providers.anthropic.key', 'test-key');

    Ai::fakeAgent(CupidaAgent::class, [['ean' => '9788412976137', 'pitch' => 'x', 'match_line' => 'y']]);

    $component = cupidaDeck();

    $index = $component->get('seed') % count((array)__('cupida.start.matches'));

    $promise = array_values((array)__('cupida.start.matches'))[$index];
    $kicker = array_values((array)__('cupida.result.kickers'))[$index];

    foreach (range(0, 2) as $round) {
        swipeThroughRound($component);
    }

    $component->call('recommend')
        ->assertSee($kicker);

    CupidaAgent::assertPrompted(function(AgentPrompt $prompt) use ($promise): bool {
        expect($prompt->prompt)->toStartWith("Le prometiste {$promise}.");

        return true;
    });
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

it('puts the shop\'s own synopsis under the pitch, on the written path and the canned one', function(): void {
    /* The pitch is the librera telling you to read this; the synopsis is what
       the book is about, in the catalog's words. A reader deciding wants both,
       and the second one does not depend on anything having been written --
       which is what makes the no-key panel worth reading at all. */
    config(['ai.providers.anthropic.key' => null]);

    $component = cupidaDeck();

    foreach (range(0, 2) as $round) {
        swipeThroughRound($component);
    }

    $component->call('recommend')->assertSet('written', false);

    $book = app(CupidaCatalog::class)->book((string)$component->get('chosen'));

    expect($book['synopsis'])->not->toBeEmpty();

    $component->assertSee(__('cupida.result.synopsis'))
        ->assertSee($book['synopsis']);
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

/* A reader who has just been handed a book wants to tell somebody, and this
   page keeps no session a link could reopen -- so what is shared is the book's
   own address, which is ours when the book was filed and the shop's when it
   was not. */
it('offers the recommendation to be shared, pointed at the book rather than at the session', function(): void {
    config(['ai.providers.anthropic.key' => null]);

    $component = cupidaDeck();

    foreach (range(0, 2) as $round) {
        swipeThroughRound($component);
    }

    $component->call('recommend');

    $recommendation = $component->viewData('recommendation');

    expect($recommendation->author)->not->toBeNull();

    $component->assertSee(__('cupida.result.share'))
        ->assertSeeHtml('data-share-url="' . e($recommendation->url) . '"')
        ->assertSee(__('cupida.result.share_message_by', [
            'title'  => $recommendation->title,
            'author' => $recommendation->author,
        ]));
});

/* A mood is defined in three places -- keywords in config, a label in lang,
   an icon in config -- and a mood added to two of them is a card that
   renders without its picture and says nothing about it. */
it('has an icon for every mood, and every icon is a heroicon that exists', function(): void {
    $moods = array_keys((array)config('cupida.moods'));
    $icons = (array)config('cupida.mood_icons');

    expect(array_keys($icons))->toEqualCanonicalizing($moods);

    /* A name that resolves to no file throws from `svg()`, naming the icon:
       that is the assertion. */
    foreach ($icons as $icon) {
        expect(svg("heroicon-o-{$icon}")->toHtml())->toContain('<svg');
    }
});

it('draws the mood cards with an icon, and only those', function(): void {
    $component = cupidaDeck();

    foreach (range(0, 1) as $round) {
        foreach ($component->viewData('cards') as $card) {
            expect($card->icon)->toBeNull();
        }

        $component->assertDontSeeHtml('[stroke-width:1]');

        swipeThroughRound($component);
    }

    $component->assertSet('round', 2);

    foreach ($component->viewData('cards') as $card) {
        expect($card->icon)->toBe(config("cupida.mood_icons.{$card->key}"))
            ->and($card->icon)->not->toBeNull();
    }

    /* The icon is in the card and not a decoration on the page: it is the
       one element with the thin stroke, and it is only there once the deck
       is dealing moods. */
    $component->assertSeeHtml('[stroke-width:1]');
});

/* The kind label is the card's top. The card is `justify-between`, and a theme
   card carries neither face nor icon: with the label hidden it had a single
   child, a lone child in a `justify-between` column sits at the start, and the
   title climbed to the head of the card with the whole lower half left empty.
   That is what `hidden wide:block` on the label bought a phone, so the label
   is rendered at every width and the title is once again the last child. */
it('heads every card with its kind at every width, and keeps the title under it', function(): void {
    $component = cupidaDeck();

    foreach (range(0, 2) as $round) {
        $component->assertSeeHtml('class="m-0 shrink-0 text-[13px] font-bold tracking-[0.22em] uppercase opacity-70"');

        /* Only the top three cards are in the DOM; the rest of the round is
           dealt as those leave. */
        foreach (array_slice($component->viewData('cards'), 0, 3) as $card) {
            $html = $component->html();

            expect($html)->toContain(__("cupida.kinds.{$card->kind}"))
                /* The label before the heading, which is what puts the type at
                   the foot of a card spread by `justify-between`. */
                ->and(strpos($html, __("cupida.kinds.{$card->kind}")))
                ->toBeLessThan(strpos($html, e($card->label)));
        }

        swipeThroughRound($component);
    }
});
