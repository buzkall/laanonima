<?php

use App\Livewire\Cupida;
use App\Models\User;
use App\Settings\CupidaSettings;
use App\Support\Cupida\CupidaCatalog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

it('opens on the section\'s own card, not on a question', function(): void {
    $this->get(route('cupida'))
        ->assertOk()
        ->assertSee(__('cupida.start.greeting', ['name' => __('cupida.start.guest')]))
        ->assertSee(__('cupida.start.button'))
        ->assertSee('la-cupida')
        ->assertDontSee(__('cupida.questions.theme'));
});

/* The opening card is one action with two controls, and Enter is the third:
   a reader on a laptop takes it from the keyboard without reaching for the
   pointer, the way the arrow keys answer the deck once it is running. */
it('starts the deck when a reader presses enter on the opening card', function(): void {
    livewire(Cupida::class)
        ->assertSeeHtml('@keydown.window.enter.prevent="$wire.start()"')
        ->assertSet('started', false)
        ->call('start')
        ->assertSet('started', true);
});

/* The end of a drag is bound to the window, not to the stack, and it has to
   stay that way. `setPointerCapture()` is the only reason a pointer that has
   left the card still reports back to it, and the browser hands that capture
   back on its own -- so a `pointerup` bound to the stack is a `pointerup` that
   sometimes never arrives, and the card sits tilted and stamped and undroppable
   until the arrow keys answer it. It looks like nothing, because nothing throws. */
it('finishes the swipe on the window, so a lost pointer capture cannot strand a card', function(): void {
    livewire(Cupida::class)
        ->call('start')
        ->assertSeeHtml('@pointerdown="grab($event)"')
        ->assertSeeHtml('@pointermove.window="drag($event)"')
        ->assertSeeHtml('@pointerup.window="release($event)"')
        ->assertSeeHtml('@pointercancel.window="cancel($event)"');
});

/*
 | The opening hint. The deck has three inputs and only two of them announce
 | themselves -- the buttons are on the screen, the arrow keys are named in a
 | line that is hidden below `wide:`, and on a phone nothing at all says the
 | card can be thrown. The first card throws itself a little, each way, once.
 |
 | The motion is the browser's business; what is pinned here is the flag that
 | decides whether it runs, and the attribute that carries it across.
 */
it('offers to show a reader who has answered nothing how the card moves', function(): void {
    livewire(Cupida::class)
        ->call('start')
        ->assertSet('coached', false)
        ->assertSeeHtml('cupidaDeck({ coach: true })');
});

it('stops showing it the moment a card has been answered', function(): void {
    $component = livewire(Cupida::class)->call('start');

    /* Read back off the render rather than guessed: the deck is rebuilt from
       its seed on every request, which is also how the page itself works. */
    $component->call('swipe', $component->viewData('cards')[0]->answer(), true)
        ->assertSet('coached', true)
        ->assertSeeHtml('cupidaDeck({ coach: false })');
});

/* `restart()` resets every other field on the component and deliberately not
   this one -- the same argument that sends it back to the deck rather than to
   the opening card. A reader asking for another book has just spent eighteen
   swipes performing the gesture; explaining it to them now would be the page
   not paying attention. Without this test the omission reads as a bug and the
   next person tidies it away. */
it('does not explain the gesture again to a reader asking for another book', function(): void {
    $component = livewire(Cupida::class)->call('start');

    $component->call('swipe', $component->viewData('cards')[0]->answer(), true)
        ->call('restart')
        ->assertSet('coached', true)
        ->assertSeeHtml('cupidaDeck({ coach: false })');
});

/* The hint is over in a few seconds and a reader who blinked has no other
   way back to it. The button between the two answers replays it, words and
   all -- and the words are the shop's, out of the settings, which is what
   the last assertion is really checking: that the seeded text reaches the
   deck at all. */
it('lets a reader ask for the explanation again', function(): void {
    livewire(Cupida::class)
        ->call('start')
        ->assertSeeHtml('@click="explain()"')
        ->assertSee(__('cupida.swipe.explain'))
        ->assertSeeHtml('role="status"')
        ->assertSee('Descartar también es elegir.');
});

it('shows no strip when the shop has left the text empty', function(): void {
    $settings = app(CupidaSettings::class);
    $settings->coach_text = '';
    $settings->save();

    livewire(Cupida::class)
        ->call('start')
        ->assertSeeHtml('@click="explain()"')
        ->assertDontSeeHtml('role="status"');
});

it('greets a reader who is signed in by their first name', function(): void {
    $this->actingAs(User::factory()->create(['name' => 'Marta Llamas']));

    $this->get(route('cupida'))
        ->assertOk()
        ->assertSee(__('cupida.start.greeting', ['name' => 'Marta']))
        ->assertDontSee(__('cupida.start.greeting', ['name' => __('cupida.start.guest')]));
});

/*
 | The demo link. /la-cupida/lorena is the page opened for one person, and the
 | name comes out of `cupida.guests` rather than out of the URL -- so the shop
 | decides what its own page says hello to.
 */
it('greets a guest the URL names', function(): void {
    config()->set('cupida.guests', ['lorena' => 'Lorena']);

    $this->get(route('cupida.guest', 'lorena'))
        ->assertOk()
        ->assertSee(__('cupida.start.greeting', ['name' => 'Lorena']))
        ->assertDontSee(__('cupida.start.greeting', ['name' => __('cupida.start.guest')]));
});

it('lets the demo link outrank whoever is signed in on the browser', function(): void {
    config()->set('cupida.guests', ['lorena' => 'Lorena']);

    $this->actingAs(User::factory()->create(['name' => 'Marta Llamas']));

    $this->get(route('cupida.guest', 'lorena'))
        ->assertOk()
        ->assertSee(__('cupida.start.greeting', ['name' => 'Lorena']))
        ->assertDontSee(__('cupida.start.greeting', ['name' => 'Marta']));
});

/* The name is written onto the shop's own public page, so a segment nobody put
   on the list is a 404 and never a word a stranger chose. */
it('does not greet a name nobody put on the list', function(): void {
    config()->set('cupida.guests', ['lorena' => 'Lorena']);

    $this->get(route('cupida.guest', 'quienquiera'))->assertNotFound();
});

it('keeps greeting the guest once the deck is running', function(): void {
    livewire(Cupida::class, ['guest' => 'Lorena'])
        ->assertSee(__('cupida.start.greeting', ['name' => 'Lorena']))
        ->call('start')
        ->call('restart')
        ->assertSet('guest', 'Lorena');
});

/*
 | The word is drawn from the deck's own seed, so which one turns up is not
 | this test's business -- that one of them does, and that no reader is left
 | looking at the raw `:match` placeholder, is.
 |
 | The opening card sets the promise one sentence per line, so only the last
 | sentence -- the one carrying the word -- survives as a contiguous run of
 | text in the markup. That is the sentence to look for.
 */
it('promises the reader one of its four words for a book', function(): void {
    $lines = array_map(
        fn(string $word): string => (string)Str::of(__('cupida.start.promise', ['match' => $word]))
            ->split('/(?<=\.)\s+/')
            ->last(),
        (array)__('cupida.start.matches'),
    );

    $page = $this->get(route('cupida'))->assertOk()->getContent();

    expect(collect($lines)->contains(fn(string $line): bool => str_contains((string)$page, $line)))
        ->toBeTrue('The opening card carries none of the four promises.');
});

it('deals the first question once a reader starts', function(): void {
    livewire(Cupida::class)
        ->call('start')
        ->assertSee(__('cupida.questions.theme'))
        ->assertSee(__('cupida.progress', ['current' => 1, 'total' => 3]))
        ->assertSee(__('cupida.progress_short', ['current' => 1, 'total' => 3]))
        ->assertSee(__('cupida.swipe.help'));
});

it('is reachable from every page', function(): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('cupida'))
        ->assertSee(__('cupida.nav'));
});

it('deals a full deck for the round it is on', function(): void {
    livewire(Cupida::class)
        ->call('start')
        ->assertSet('round', 0)
        ->assertViewHas('rounds', 3)
        ->assertViewHas('cards', fn(array $cards): bool => count($cards) === config('cupida.deck.size'));
});

it('keeps a long label inside the card it is written on', function(): void {
    /* A card is about 197px wide on a phone and its heading's floor is 30px, so
       a long word is wider than the box it is written in and has nowhere to go.
       Measured on "Vidas contemporáneas" at that size: 357px of text in a 149px
       column, and the card overflowing its own edge by 194px -- which paints
       over the cards behind it in the stack, where a reader sees a stray "neas"
       beside the top card.

       `hyphens-auto` needs the page's `lang="es"` and breaks the word where
       Spanish breaks it; `break-words` is the half that catches a name, which
       is most of what round two deals and which no dictionary splits. Neither
       is decorative, and `overflow-hidden` is what makes the bleed impossible
       rather than unlikely -- so all three are pinned here. */
    livewire(Cupida::class)
        ->call('start')
        ->assertSeeHtml('cupida-card overflow-hidden')
        ->assertSeeHtml('text-balance hyphens-auto break-words');
});

it('says so plainly when there is no catalog to deal from', function(): void {
    config()->set('cupida.data_path', base_path('tests/Fixtures/cupida/nothing-here'));

    $this->get(route('cupida'))
        ->assertOk()
        ->assertSee(__('cupida.empty.heading'))
        ->assertDontSee(__('cupida.start.button'));
});

it('says so in the log when its catalog will not parse', function(): void {
    $directory = base_path('tests/Fixtures/cupida/tmp-' . str()->random(8));

    File::makeDirectory($directory, recursive: true);
    File::put("{$directory}/books.json", '[{"ean": "9780000000001",]');

    config()->set('cupida.data_path', $directory);
    app()->forgetInstance(CupidaCatalog::class);

    Log::shouldReceive('warning')
        ->atLeast()->once()
        ->withArgs(fn(string $message): bool => str_contains($message, 'could not read its catalog'));

    /* Still the quiet empty page for the reader -- loud only in the log. */
    $this->get(route('cupida'))
        ->assertOk()
        ->assertSee(__('cupida.empty.heading'));

    File::deleteDirectory($directory);
});

/*
 | La Cupida is the one shelf page with a face of its own, so it is the one
 | that shares with a picture. The other shelves pass no `:og-image` and get
 | the small card instead of a wide one with nothing in it.
 */
it('shares with the cupida card behind it', function(): void {
    $card = Vite::asset('resources/images/brand/la-cupida-og.jpg');

    $this->get(route('cupida'))
        ->assertOk()
        ->assertSee('<meta property="og:image" content="' . $card . '" />', escape: false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image" />', escape: false);
});

it('shares the guest page with the same card', function(): void {
    config()->set('cupida.guests', ['lorena' => 'Lorena']);

    $this->get(route('cupida.guest', 'lorena'))
        ->assertOk()
        ->assertSee(Vite::asset('resources/images/brand/la-cupida-og.jpg'), escape: false);
});

it('leaves a shelf with no face of its own without a picture', function(): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('og:image')
        ->assertSee('<meta name="twitter:card" content="summary" />', escape: false);
});

/*
 | Every panel of this page is measured to fill a phone screen exactly, so the
 | one line of footer under it is a strip of cream the deck wants back. The
 | line still stands from `wide:` up, and it stands at every width on the other
 | page that wears the same short footer -- the request form.
 */
it('takes the footer off a phone, and only on this page', function(): void {
    $hiddenFooter = 'py-[clamp(16px,2.5vw,26px)] wide:block hidden';

    $this->get(route('cupida'))
        ->assertOk()
        ->assertSee($hiddenFooter, escape: false);

    $this->actingAs(User::factory()->client()->create())
        ->get(route('book-requests.create'))
        ->assertOk()
        ->assertDontSee($hiddenFooter, escape: false);
});
