<?php

use App\Models\Book;
use App\Models\CupidaRecommendation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function(): void {
    Storage::fake('og');
    useCupidaFixture();
});

it('shows what La Cupida wrote, not just the book it wrote about', function(): void {
    $recommendation = CupidaRecommendation::factory()->create([
        'title'      => 'Mientras pasan otras cosas',
        'author'     => 'Sara Torres',
        'pitch'      => 'Se lee de una sentada y se piensa durante una semana.',
        'match_line' => 'Porque dijiste que sí a la poesía.',
    ]);

    $this->get(route('cupida.recommendation', $recommendation))
        ->assertOk()
        ->assertSee('Mientras pasan otras cosas')
        ->assertSee('Sara Torres')
        ->assertSee('Se lee de una sentada y se piensa durante una semana.')
        ->assertSee('Porque dijiste que sí a la poesía.')
        ->assertSee(__('cupida.shared.kicker'))
        ->assertSee(__('cupida.shared.cta'));
});

/* The key is the whole of the authorization, so the page has to be reachable
   with no session and no account behind it -- that is the point of a link
   somebody was handed. */
it('opens for anybody holding the link', function(): void {
    $recommendation = CupidaRecommendation::factory()->create();

    $this->assertGuest();

    $this->get(route('cupida.recommendation', $recommendation))->assertOk();
});

it('404s on a key nobody was given', function(): void {
    CupidaRecommendation::factory()->create();

    $this->get('/la-cupida/recomendacion/01hzzzzzzzzzzzzzzzzzzzzzzz')->assertNotFound();

    /* And the old shape of key is not a shortcut back in. */
    $this->get('/la-cupida/recomendacion/1')->assertNotFound();
});

/*
 | The row records who asked and what they said yes and no to. None of it is the
 | page's business: a reader sending somebody a book is not sending them their
 | own session.
 */
it('says nothing about the reader whose session it was', function(): void {
    $reader = User::factory()->create(['name' => 'Marta Llamas', 'email' => 'marta@example.test']);

    $recommendation = CupidaRecommendation::factory()->create([
        'user_id' => $reader->id,
        'likes'   => ['theme:FM', 'mood:heartbreak'],
        'passes'  => ['theme:WB'],
    ]);

    $this->get(route('cupida.recommendation', $recommendation))
        ->assertOk()
        ->assertDontSee('Marta Llamas')
        ->assertDontSee('marta@example.test')
        ->assertDontSee('heartbreak')
        ->assertDontSee('theme:FM');
});

/* One page per game played, each about a book that has a page of its own. */
it('keeps itself out of the index', function(): void {
    $recommendation = CupidaRecommendation::factory()->create();

    $this->get(route('cupida.recommendation', $recommendation))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex" />', escape: false);
});

it('links to our page for the book when we have one, and offers a game when we do not', function(): void {
    $ours = Book::factory()->create(['title' => 'Ollis']);

    $filed = CupidaRecommendation::factory()->create(['book_id' => $ours->id, 'title' => 'Ollis']);
    $loose = CupidaRecommendation::factory()->create(['book_id' => null]);

    $this->get(route('cupida.recommendation', $filed))
        ->assertOk()
        ->assertSee(__('cupida.result.read_more'))
        ->assertSee(route('books.show', $ours));

    $this->get(route('cupida.recommendation', $loose))
        ->assertOk()
        ->assertDontSee(__('cupida.result.read_more'))
        ->assertSee(__('cupida.shared.cta'));
});

/* The panel it is drawn from measures itself against a phone screen; this page
   is a document and has nothing to fit, so the pitch is simply open. */
it('does not clamp the pitch it exists to show', function(): void {
    $recommendation = CupidaRecommendation::factory()->create();

    $this->get(route('cupida.recommendation', $recommendation))
        ->assertOk()
        ->assertDontSee('cupida-pitch')
        ->assertDontSee('x-data');
});

it('draws a card for the link preview, once', function(): void {
    $recommendation = CupidaRecommendation::factory()->create();

    $this->get(route('cupida.recommendation', $recommendation))->assertOk();

    $cards = Storage::disk('og')->files('cupida');

    expect($cards)->toHaveCount(1);

    $written = Storage::disk('og')->lastModified($cards[0]);

    $this->get(route('cupida.recommendation', $recommendation))
        ->assertOk()
        ->assertSee($cards[0]);

    expect(Storage::disk('og')->files('cupida'))->toBe($cards)
        ->and(Storage::disk('og')->lastModified($cards[0]))->toBe($written);
});
