<?php

namespace App\Livewire;

use App\Actions\Cupida\RecommendBook;
use App\Support\Cupida\CupidaCard;
use App\Support\Cupida\CupidaCatalog;
use App\Support\Cupida\CupidaDeck;
use App\Support\Cupida\Recommendation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Three questions, eighteen swipes, one book.
 *
 * Almost nothing is kept between requests. The deck is rebuilt from its seed on
 * every render rather than stored, and the recommendation is kept as an EAN and
 * two strings rather than as the object the view draws -- a Recommendation
 * carries a CoverPalette and, when we stock the book, an Eloquent model, none
 * of which belongs in a payload the browser round-trips. Both are cheap to
 * rebuild: the catalog is a singleton that has already read its files.
 *
 * Everything a reader could tamper with is `#[Locked]`. The answers decide what
 * goes into a prompt the shop pays for, and the seed decides the deck; both are
 * the component's to set, never the browser's.
 *
 * Writing the recommendation is a second round trip, on purpose. The swipe that
 * ends the third round returns immediately and the reader watches the "pensando"
 * panel while the model works, rather than watching a card that will not fly off
 * for four seconds.
 */
class Cupida extends Component
{
    #[Locked]
    public int $seed = 0;

    /**
     * Who the opening card says hello to, when the URL already knows.
     *
     * Set by /la-cupida/{guest} out of the `cupida.guests` whitelist, so it is
     * a name the shop chose rather than one a visitor typed. It outranks the
     * signed-in user's own name: somebody opening the demo link wants the demo,
     * whoever happens to be logged in on that browser.
     */
    #[Locked]
    public ?string $guest = null;

    /**
     * Whether the reader has been past the opening card.
     *
     * The deck used to start dealing the moment the page loaded, which meant
     * the first thing anyone met was a card with no explanation of what to do
     * with it. The opening screen is where the section says its own name.
     */
    #[Locked]
    public bool $started = false;

    #[Locked]
    public int $round = 0;

    /** @var array<int, string> answers as "kind:key" */
    #[Locked]
    public array $likes = [];

    /** @var array<int, string> */
    #[Locked]
    public array $passes = [];

    /** @var array<int, string> the cards already answered in the round being swiped */
    #[Locked]
    public array $answered = [];

    #[Locked]
    public bool $thinking = false;

    #[Locked]
    public ?string $chosen = null;

    #[Locked]
    public ?string $pitch = null;

    #[Locked]
    public ?string $matchLine = null;

    #[Locked]
    public bool $written = false;

    private ?CupidaDeck $deck = null;

    public function mount(?string $guest = null): void
    {
        $this->guest = $guest;
        $this->seed = random_int(0, PHP_INT_MAX);

        /* A pinned deck is worth having for a screenshot and worth refusing
           everywhere else: it is the one way to make two readers see the same
           three questions, which is exactly what this page is not for. */
        if (app()->isLocal() && request()->filled('seed')) {
            $this->seed = request()->integer('seed');
        }
    }

    public function start(): void
    {
        $this->started = true;
    }

    /**
     * Record one swipe and, when the round runs out, move on.
     *
     * A card already answered is ignored rather than counted twice: the gesture
     * and the buttons can both fire for one card when a pointer is released
     * over the button the card flew past.
     */
    public function swipe(string $answer, bool $liked): void
    {
        if ($this->thinking || $this->chosen !== null) {
            return;
        }

        $cards = $this->deck()->round($this->round);
        $known = array_map(fn(CupidaCard $card): string => $card->answer(), $cards);

        if (! in_array($answer, $known, true) || in_array($answer, $this->answered, true)) {
            return;
        }

        $this->answered[] = $answer;

        if ($liked) {
            $this->likes[] = $answer;
        } else {
            $this->passes[] = $answer;
        }

        if (count($this->answered) < count($cards)) {
            return;
        }

        $this->answered = [];
        $this->advance();
    }

    /**
     * On to the next round that actually has cards.
     *
     * A round comes up empty when the catalog has nothing to fill it with --
     * a pool with no authors on record, say. A round with no cards can never be
     * completed, so it is stepped over rather than left to strand the session.
     */
    private function advance(): void
    {
        $deck = $this->deck();

        do {
            $this->round++;
        } while ($this->round < $deck->rounds() && $deck->round($this->round) === []);

        if ($this->round >= $deck->rounds()) {
            $this->thinking = true;
        }
    }

    /**
     * Ask for the book.
     *
     * Called by wire:init once the thinking panel is on screen, so the reader
     * sees it before anything waits on a model.
     */
    public function recommend(RecommendBook $recommend): void
    {
        if (! $this->thinking || $this->chosen !== null) {
            return;
        }

        $recommendation = $recommend(
            likes: $this->likes,
            passes: $this->passes,
            /* Past the limit the reader still gets a book -- the shortlist
               picks it -- they just do not get a written pitch. */
            write: $this->withinRateLimit(),
            seed: $this->seed,
            /* The phrase the opening card promised, so the pitch can keep it. */
            promise: $this->matchWord(),
        );

        $this->thinking = false;

        if (! $recommendation instanceof Recommendation) {
            return;
        }

        $this->chosen = $recommendation->ean;
        $this->pitch = $recommendation->pitch;
        $this->matchLine = $recommendation->matchLine;
        $this->written = $recommendation->written;
    }

    public function restart(): void
    {
        $this->seed = random_int(0, PHP_INT_MAX);

        /* Straight back to the deck rather than to the opening card: somebody
           asking for another book has read the instructions once already. */
        $this->started = true;
        $this->round = 0;
        $this->likes = [];
        $this->passes = [];
        $this->answered = [];
        $this->thinking = false;
        $this->chosen = null;
        $this->pitch = null;
        $this->matchLine = null;
        $this->written = false;
    }

    public function render(): View
    {
        $deck = $this->deck();

        return view('livewire.cupida', [
            'rounds' => $deck->rounds(),
            'cards'  => array_values(array_filter(
                $deck->round($this->round),
                fn(CupidaCard $card): bool => ! in_array($card->answer(), $this->answered, true),
            )),
            'recommendation' => $this->recommendation(),
            'empty'          => $this->catalog()->isEmpty(),
            'greeting'       => $this->greetingName(),
            'match'          => $this->matchWord(),
            'kicker'         => $this->kicker(),
        ]);
    }

    /**
     * Who the opening card says hello to.
     *
     * A guest named by the URL first, then the reader who is signed in, then
     * nobody. The first word of the name and not the whole of it: the card is
     * saying hello, and a full name on a greeting reads like a bank. A reader
     * who is not signed in is greeted as the shop itself -- anónima is what
     * they are, and it is the shop's own name.
     */
    private function greetingName(): string
    {
        if ($this->guest !== null) {
            return $this->guest;
        }

        $name = trim((string)auth()->user()?->name);

        return $name === ''
            ? (string)__('cupida.start.guest')
            : (string)str($name)->before(' ');
    }

    /**
     * A cita, a flechazo, a crush or a match -- what the card promises.
     *
     * Drawn from the seed rather than at random on each render: the same
     * session keeps the same promise, so a Livewire re-render of the opening
     * card cannot reword the sentence a reader is halfway through.
     */
    private function matchWord(): string
    {
        return $this->promised('cupida.start.matches');
    }

    /**
     * The heading over the book, in the same word the opening card promised:
     * "Tu flechazo" over a book that was promised as a flechazo. It was a
     * fixed "Tu cita" while the promise varied, which broke the loop the first
     * screen opens.
     */
    private function kicker(): string
    {
        return $this->promised('cupida.result.kickers');
    }

    /**
     * The two lists are parallel and the same seed picks the same index from
     * both, which is what keeps the opening card and the result panel telling
     * one story.
     */
    private function promised(string $key): string
    {
        $words = array_values((array)__($key));

        return (string)$words[$this->seed % count($words)];
    }

    /**
     * Rebuild what the result panel draws from the EAN we kept.
     */
    private function recommendation(): ?Recommendation
    {
        if ($this->chosen === null) {
            return null;
        }

        $book = $this->catalog()->book($this->chosen);

        return $book === null
            ? null
            : Recommendation::make(
                book: $book,
                pitch: (string)$this->pitch,
                matchLine: $this->matchLine,
                written: $this->written,
            );
    }

    private function deck(): CupidaDeck
    {
        /* Rebuilt per request out of the seed, which is all the component
           stores of it: the same seed always deals the same three rounds. */
        return $this->deck ??= CupidaDeck::for($this->catalog(), $this->seed);
    }

    private function catalog(): CupidaCatalog
    {
        return app(CupidaCatalog::class);
    }

    /**
     * The page is public and every written recommendation costs money, so one
     * address gets a fixed number an hour. This is the only thing standing
     * between the demo and somebody's script.
     */
    private function withinRateLimit(): bool
    {
        $key = 'cupida:' . request()->ip();

        /* Counted rather than asked with tooManyAttempts(), which answers false
           until a timer key exists and so lets the first call through however
           low the limit is set. */
        if (RateLimiter::attempts($key) >= (int)config('cupida.rate_limit.attempts')) {
            return false;
        }

        RateLimiter::hit($key, (int)config('cupida.rate_limit.per'));

        return true;
    }
}
