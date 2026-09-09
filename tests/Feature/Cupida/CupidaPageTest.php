<?php

use App\Livewire\Cupida;
use App\Models\User;
use App\Support\Cupida\CupidaCatalog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;

use function Pest\Livewire\livewire;

it('opens on the section\'s own card, not on a question', function(): void {
    $this->get(route('cupida'))
        ->assertOk()
        ->assertSee(__('cupida.start.greeting', ['name' => __('cupida.start.guest')]))
        ->assertSee(__('cupida.start.button'))
        ->assertSee('la-cupida')
        ->assertDontSee(__('cupida.questions.theme'));
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
 */
it('promises the reader one of its four words for a book', function(): void {
    $lines = array_map(
        fn(string $word): string => (string)__('cupida.start.promise', ['match' => $word]),
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
