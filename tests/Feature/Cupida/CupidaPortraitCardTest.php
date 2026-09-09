<?php

use App\Livewire\Cupida;
use App\Support\Cupida\CupidaCard;
use App\Support\Cupida\CupidaCatalog;
use App\Support\Cupida\CupidaDeck;
use App\Support\Cupida\CupidaPortrait;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function(): void {
    /* Fakes the portraits disk too, so nothing here can see whatever
       `cupida:portraits:fetch` last left in storage. */
    useCupidaFixture();
});

/** The five fixture authors who have a face, on the disk. */
function haveEveryPortrait(): void
{
    foreach (['guerriero-leila', 'bermejo-ana', 'rodriguez-elaine-vilar', 'mayayo-patricia', 'parker-sarah-a'] as $slug) {
        havePortrait("{$slug}.jpg");
    }
}

/** Put a portrait on the disk the way `cupida:portraits:fetch` would. */
function havePortrait(string $file): void
{
    Storage::disk('portraits')->put($file, fakeCover(480, 640));
}

/** The author cards of a deck, keyed by slug, across enough seeds to see them all. */
function authorCardsAcrossSeeds(int $seeds = 40): array
{
    $catalog = app(CupidaCatalog::class);
    $cards = [];

    foreach (range(1, $seeds) as $seed) {
        foreach (CupidaDeck::for($catalog, $seed)->round(1) as $card) {
            $cards[$card->key] = $card;
        }
    }

    return $cards;
}

it('gives an author card the face we downloaded for them', function(): void {
    havePortrait('guerriero-leila.jpg');

    $portrait = app(CupidaCatalog::class)->portrait('guerriero-leila');

    expect($portrait)->toBeInstanceOf(CupidaPortrait::class)
        ->and($portrait->color)->toBe('#8a7f74')
        ->and($portrait->artist)->toBe('Florenciac')
        ->and($portrait->license)->toBe('CC BY-SA 4.0')
        ->and($portrait->url())->toContain('guerriero-leila.jpg');
});

it('deals a faceless card when the metadata names a photo this machine never fetched', function(): void {
    /* The whole reason the JPEGs are not committed is worth one assertion: a
       deploy that skipped `cupida:portraits:fetch` has every row and no image,
       and the page must fall back to the card it dealt before portraits
       existed rather than to a broken img. */
    expect(app(CupidaCatalog::class)->portrait('guerriero-leila'))->toBeNull();
});

it('deals a faceless card for an author nobody has photographed', function(): void {
    havePortrait('vera-julia.jpg');

    /* Even with a stray file of the right name on disk: the verdict is
       no_image, so there is nothing to show. */
    expect(app(CupidaCatalog::class)->portrait('vera-julia'))->toBeNull();
});

it('deals a faceless card for an author with no row at all', function(): void {
    expect(app(CupidaCatalog::class)->portrait('kingfisher-t'))->toBeNull();
});

it('refuses a matched row that names no file', function(): void {
    /* A corrupt row, not a face. */
    expect(app(CupidaCatalog::class)->portrait('torres-sara'))->toBeNull();
});

it('never deals a collective as a card', function(): void {
    /* "¿Te gusta Vv. Aa.?" is a question nobody can answer by swiping. Kaoru
       Ito stands in for it in the fixture and is marked no_person. */
    expect(app(CupidaCatalog::class)->isCollective('ito-kaoru'))->toBeTrue()
        ->and(array_keys(authorCardsAcrossSeeds()))
        ->not->toContain('ito-kaoru')
        ->and(array_keys(authorCardsAcrossSeeds()))->toContain('guerriero-leila');
});

it('carries the portrait onto the card the reader swipes', function(): void {
    havePortrait('guerriero-leila.jpg');

    $card = authorCardsAcrossSeeds()['guerriero-leila'] ?? null;

    expect($card)->toBeInstanceOf(CupidaCard::class)
        ->and($card->portrait)->toBeInstanceOf(CupidaPortrait::class);
});

it('leaves theme and mood cards without a portrait', function(): void {
    havePortrait('guerriero-leila.jpg');

    $deck = CupidaDeck::for(app(CupidaCatalog::class), 1234);

    foreach ([0, 2] as $round) {
        foreach ($deck->round($round) as $card) {
            expect($card->portrait)->toBeNull();
        }
    }
});

it('reads no portraits at all when the file is not there', function(): void {
    /* A fresh clone, and the repository the day this merges. */
    config()->set('cupida.data_path', base_path('tests/Fixtures/cupida/nothing-here'));
    app()->forgetInstance(CupidaCatalog::class);

    expect(app(CupidaCatalog::class)->portraits())->toBeEmpty()
        ->and(app(CupidaCatalog::class)->portrait('guerriero-leila'))->toBeNull()
        ->and(app(CupidaCatalog::class)->isCollective('ito-kaoru'))->toBeFalse();
});

it('renders the portrait onto the card the reader actually sees', function(): void {
    /* The blade is the last mile and nothing else exercises it: every other
       assertion in this file stops at the CupidaPortrait object.

       Five of the ten authors in the pool have a face and six are dealt, so at
       least one face is always somewhere in the round whatever seed the
       component picks. That is not the same as one being *rendered*: the deck
       draws `array_slice($cards, 0, 3)`, and the three faceless writers can
       just as easily be the three on top -- which is a one-in-twelve failure
       (10 of the 120 ways to choose three of ten) that has nothing to do with
       the portraits and everything to do with the shuffle.

       So the card with a face is swiped up to the top before anything is
       asserted about the DOM. The guarantee is then the round's, which is the
       one the pool actually gives. */
    haveEveryPortrait();

    $component = livewire(Cupida::class)->call('start');

    /* Round one is themes; round two is the authors. */
    foreach ($component->viewData('cards') as $card) {
        $component->call('swipe', $card->answer(), true);
    }

    $authors = $component->viewData('cards');

    expect(collect($authors)->pluck('kind')->unique()->all())->toBe(['author']);

    $first = collect($authors)->search(fn(CupidaCard $card): bool => $card->portrait instanceof CupidaPortrait);

    expect($first)->not->toBeFalse('No card in the authors round carries a face.');

    /* Answering a card takes it out of the next render, so the writer with a
       face is on top once everyone ahead of them has been answered. */
    foreach (array_slice($authors, 0, $first) as $faceless) {
        $component->call('swipe', $faceless->answer(), true);
    }

    $component->assertSee('<img', escape: false)
        ->assertSee($authors[$first]->portrait->file, escape: false);
});

it('shortens a photographer credit that is really a provenance note', function(): void {
    havePortrait('guerriero-leila.jpg');

    /* Commons' Artist field is free text. Tolkien's portrait credits "Unknown
       photo studio commissioned by Tolkien's students 1925/6 (private
       communication from Catherine McIlwaine, Tolkien Archivist, Bodleian
       Library)", which runs to three lines under the deck. */
    $portrait = app(CupidaCatalog::class)->portrait('guerriero-leila');

    expect($portrait->artistLabel())->toBe('Florenciac');

    $long = 'Unknown photo studio commissioned by Tolkien\'s students 1925/6 (private communication from Catherine McIlwaine)';

    expect(mb_strlen((string)CupidaPortrait::fromPool([
        'status' => 'matched',
        'photo'  => 'x.jpg',
        'credit' => ['artist' => $long],
    ])->artistLabel()))->toBeLessThanOrEqual(63);
});

it('credits the photographer of the card in front of the reader', function(): void {
    haveEveryPortrait();

    $component = livewire(Cupida::class)->call('start');

    foreach ($component->viewData('cards') as $card) {
        $component->call('swipe', $card->answer(), true);
    }

    /* The credit follows the top card and nothing else, so walk the author
       round one swipe at a time until a face is on top. Five of the ten authors
       in the pool have one and six are dealt, so this always finds one. */
    $credited = false;

    while ($top = $component->viewData('cards')[0] ?? null) {
        if ($top->portrait instanceof CupidaPortrait) {
            /* CC BY-SA wants the photographer named, the license given and the
               change indicated -- every portrait is recropped to the card, so
               "recortada" is not a flourish. */
            $component->assertSee(__('cupida.portraits.credit', [
                'artist'  => $top->portrait->artistLabel(),
                'license' => $top->portrait->license,
            ]));

            $credited = true;

            break;
        }

        /* A card with no face credits nobody. */
        $component->assertDontSee(__('cupida.portraits.credit', ['artist' => '', 'license' => '']));

        $component->call('swipe', $top->answer(), true);
    }

    expect($credited)->toBeTrue();
});

it('credits nobody when there is no photo to credit', function(): void {
    /* No images fetched, so no faces, so nothing to attribute. */
    $component = livewire(Cupida::class)->call('start');

    foreach ($component->viewData('cards') as $card) {
        $component->call('swipe', $card->answer(), true);
    }

    expect(collect($component->viewData('cards'))->pluck('portrait')->filter())->toBeEmpty();

    $component->assertDontSee('Florenciac')
        ->assertDontSee('CC BY-SA');
});
