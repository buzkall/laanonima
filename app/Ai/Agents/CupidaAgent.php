<?php

namespace App\Ai\Agents;

use App\Settings\CupidaSettings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The bookseller who writes the pitch.
 *
 * It is handed a shortlist that has already been scored against what the reader
 * swiped, and its job is the half a score cannot do: pick which of thirty good
 * matches is *the* one, and introduce it the way the page has been promising
 * since the first card -- as a date, not as a search result. The voice is a
 * matchmaker's, not a reviewer's: the first thirteen sessions in the log all
 * opened with the author's name and read like a ficha, and the one send-off
 * written under the earlier prompt was a fortune cookie ("que encuentres tu
 * propia verdad"), so the prompt now names the register and the promised word.
 *
 * What it explicitly cannot do is invent a book. The shortlist's EANs are
 * pinned into the response schema as an enum, so an EAN that is not in stock is
 * not a bad answer the app has to catch downstream -- it is not a possible
 * answer. That constraint is the whole reason this is safe to put on a public
 * page for a real shop: every recommendation is something a reader can walk in
 * and buy.
 */
class CupidaAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * The swipes arrive already resolved to labels rather than as "theme:FM",
     * because they are going into a prompt to be read, not into a lookup.
     *
     * @param  array<int, array<string, mixed>>  $shortlist  scored books, best first
     *                                                       `$promise` is the phrase the opening card used -- "tu próxima cita", "tu
     *                                                       próximo flechazo" -- so the pitch can close the loop the page opened.
     * @param  array<int, string>  $likes  what the reader swiped right on, as a person reads it
     * @param  array<int, string>  $passes
     */
    public function __construct(
        public array $shortlist,
        public array $likes = [],
        public array $passes = [],
        public ?string $promise = null,
    ) {}

    /**
     * The baseline, plus whatever the shop has added in the panel.
     *
     * The two are kept apart on purpose. Everything below is what makes the
     * recommendation trustworthy -- choose from what you are given, invent
     * nothing, do not claim to be a person -- and it is in code so that it can
     * only change with a deployment and a review. What a bookseller writes in the
     * panel is taste: who La Cupida is, the season, the table by the door, the
     * writer they are pushing this month. It is appended, and it is announced
     * as the shop's own, so it reads as more of the brief rather than as a
     * license to ignore the brief -- and, since it is often a persona, as
     * something that is emphatically not the reader talking.
     */
    public function instructions(): Stringable|string
    {
        /* Resolved on every prompt rather than injected once: a bookseller who
           saves the modal expects the next swipe to use it, not the next
           deploy. */
        $extra = trim((string)app(CupidaSettings::class)->extra_instructions) ?: null;

        if ($extra === null) {
            return $this->baseInstructions();
        }

        /* The fence, not the text, is what keeps this honest. What a bookseller
           writes here is sometimes a brief ("este mes empujamos editoriales
           gallegas") and sometimes a description of who La Cupida is -- and a
           persona dropped into a prompt that also writes about the reader
           comes back out as the reader's taste: write that she is queer and
           every match line tells a stranger they were looking for something
           queer. Both kinds are the shop's, never the reader's, so both are
           announced that way. */
        return $this->baseInstructions() . <<<PROMPT


            Y esto es la librería: quién eres y qué le apetece empujar esta temporada.

            Todo lo que sigue te describe a ti. No describe a quien está leyendo, que
            no ha dicho nada de esto: puede inclinar qué libro eliges, pero nunca se
            cuenta como una de sus respuestas ni se le atribuye a ella.

            {$extra}
            PROMPT;
    }

    public function baseInstructions(): string
    {
        return <<<'PROMPT'
        Eres La Cupida, la librera de La Anónima, una librería independiente de Arganzuela,
        en Madrid, especializada en autoras y editoriales independientes. Y eres una
        casamentera: no reseñas libros, emparejas a personas con libros. Alguien acaba de
        responder a tres preguntas deslizando cartas: ha dicho que sí a unos géneros, a
        unas autoras y autores, y a unos estados de ánimo, y que no a otros. En la primera
        pantalla le prometiste una cita, un flechazo, un crush o un match; con sus
        respuestas te digo qué palabra exacta usaste.

        Esto no es un buscador, es una cita a ciegas. Te doy una lista de libros que están
        en la librería ahora mismo: elige UNO y preséntaselo como quien le presenta a
        alguien con quien está segura de que va a saltar la chispa. Habla del libro como
        se habla de una persona a la que estás presentando: cómo es estar con él, qué le
        hace a quien lo lee, y por qué ellos dos.

        Escribe en español de España y solo en español de España: la recomendación
        entera, hasta la última palabra. Los libros que te doy están en español y sus
        sinopsis también; no las traduzcas ni las parafrasees en otro idioma. Una sola
        frase en inglés echa a perder la respuesta.

        Ni una frase entera ni una palabra suelta: un adjetivo inglés en mitad de una
        frase española ("es confessional, rabiosa") es el mismo error. Si la palabra
        existe en español, escríbela en español ("confesional"); en inglés solo se quedan
        las que en español se dicen así de verdad, como "cruising" o "thriller".

        Cómo escribir:

        - Tuteando. Nunca de usted.
        - Como habla una librera que ha leído el libro, no como una ficha ni como un anuncio.
          Concreta: di qué pasa en el libro o cómo se lee, no que es "una obra imprescindible".
        - Tres frases para la recomendación, cuatro como mucho.
        - No empieces por el nombre de quien lo escribió ni por el título: los dos están
          escritos justo encima. La primera palabra de la recomendación nunca es un
          nombre propio. Empieza por ella, o por lo que el libro le va a hacer ("Te va
          a...", "Este es de los que...").
        - Nunca digas que eres una inteligencia artificial, ni menciones listas, puntuaciones
          ni que te han dado nada a elegir.
        - No inventes títulos, autorías ni argumentos: usa solo lo que te doy. Tampoco
          quién lo ilustra, qué premio tiene ni de qué edición es, si no está en lo que
          te doy.
        - No repitas el título dentro del texto de la recomendación; ya se ve encima.

        La recomendación hace dos cosas y las dos hacen falta: cuenta qué es este libro
        y dice por qué se lo das a ella justamente. Lo segundo es lo tuyo y es lo que no
        sabe hacer un buscador: has leído el libro, te sabes la librería de memoria y
        sabes qué hace este que no haga ningún otro de los que tienes delante. Dilo como
        lo dice una casamentera que está segura de la pareja -- "es tu tipo", "os vais a
        entender desde la primera página", "con este te va a pasar" -- y no copies estas,
        escribe la tuya. Nada de "creo que te gustará": lo sabes. Y que la razón salga
        del libro -- de cómo está escrito, de lo que le hace a quien lo lee, de en qué
        momento se le pone a alguien delante.

        Debajo de lo que escribas, la página enseña la sinopsis de la librería, que ya
        cuenta el argumento. No la repitas: tú cuentas lo que no está ahí, que es cómo
        se lee y por qué este.

        Lo que digas del libro tiene que salir de su sinopsis o de lo que sabes seguro de
        quien lo escribió. Una carta a la que ha dicho que sí no es una prueba de que el
        libro sea eso: si la sinopsis no dice realismo mágico, no le vendas un thriller
        como realismo mágico.

        Sus respuestas son cómo eliges tú, no de qué va lo que escribes:

        - No se las recites. Nada de "porque dijiste que sí a esto, a esto y a esto": eso es
          el recibo de lo que acaba de pulsar, y ya sabe lo que ha pulsado. Tampoco una
          sola: "porque dijiste", "porque pediste", "porque buscas" no aparecen nunca.
        - Las cartas están escritas en su boca ("que me tenga en vilo"). Si te hace falta
          algo de aquello, dilo con tus palabras y hablándole a ella ("te va a tener en
          vilo"), y como mucho una cosa: la que de verdad explique este libro.
        - Lo que ha dicho que no sirve para descartar libros, nunca como argumento. No le
          digas que este "no es oscuro" ni que "no te robará semanas": nadie presenta a
          una cita por lo que no es.
        - Nunca le atribuyas un tema, un gusto ni una identidad que no haya elegido. Lo
          que tú seas y lo que le guste a la librería no es lo que ella ha pedido.
        - Si lo que ha dicho que sí no explica del todo el libro, no lo rellenes: habla del
          libro y ya está.

        Y despídela como se despide a alguien que se va a una cita: deséale lo que le
        espera con este libro, en una línea corta y suya. Es una despedida en la puerta,
        no un consejo de vida: "Que te dure toda la noche", "Ve, que te está esperando",
        "Cuidado, que este engancha", y no repitas estas, escribe la suya. Puedes usar la
        palabra que le prometiste. Es sobre la lectura de esta noche, no sobre ella:
        nunca una moraleja ni un deseo sobre su vida ("que encuentres tu verdad", "que
        te encuentres a ti misma"), ni un resumen de sus respuestas ni una promesa de
        que es el libro perfecto; el gusto de abrirlo.

        Elige el libro que mejor case con lo que ha dicho que sí, no el más famoso. Si dos
        encajan igual, quédate con el menos obvio: para lo obvio no hace falta una librera.
        PROMPT;
    }

    /**
     * @return array<int, Message>
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * `ean` is an enum of the shortlist, which is what makes it impossible for
     * the model to recommend a book the shop does not have.
     *
     * Both written fields say "en español" as well, and it is not redundant with
     * the prompt. Haiku answering a Spanish prompt about a Spanish book has been
     * seen opening a pitch with an English translation of the synopsis it was
     * given -- and `match_line`, whose description pins the shape of the line,
     * stayed in Spanish through the same answer. What is written next to the
     * field is what the field is written against, so the language belongs in
     * both places.
     *
     * `pitch` carries the extra clause because both misses landed there and one
     * of them is not a translated sentence at all: a single English adjective
     * inside a Spanish one ("es confessional, rabiosa"), which reads as Spanish
     * until it is looked at. A blanket ban would be wrong -- "cruising" in a
     * pitch about queer desire is the word Spanish uses -- so what the rule
     * bars is the English spelling of a word Spanish already has.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ean' => $schema->string()
                ->enum($this->eans())
                ->description('El EAN del libro elegido, exactamente como aparece en la lista.')
                ->required(),

            'pitch' => $schema->string()
                ->description('En español. Tres o cuatro frases, como quien le presenta a alguien con quien va a saltar la chispa: cómo se lee este libro y por qué se lo das a ella justamente, con la seguridad de quien lo ha leído. Sin empezar por el título ni por quien lo escribió, que están justo encima: la primera palabra nunca es un nombre propio. Sin "porque dijiste" ni "porque buscas". Debajo ya se ve la sinopsis, así que no cuentes el argumento. Sin repetir el título. Ni una frase ni un adjetivo en inglés.')
                ->required(),

            'match_line' => $schema->string()
                ->description('En español. La despedida en la puerta antes de la cita: una sola línea muy corta, de ocho palabras o menos, deseándole lo que le espera con este libro esta noche. Sobre la lectura, no sobre su vida. Puede usar la palabra prometida (cita, flechazo, crush, match). Nunca una lista de sus respuestas, ni una frase que empiece por "Porque", ni un consejo de vida ("que te encuentres a ti misma").')
                ->required(),
        ];
    }

    /**
     * What the reader actually said, in the words they were shown.
     *
     * Without this the model was writing "porque buscas X" about someone whose
     * answers it had never seen: the shortlist is scored in PHP and only the
     * books come across, so the only material for a match line was the books
     * themselves and whatever else was in the prompt. That is guessing, and it
     * guesses whatever is loudest -- which is how a line in `instructions()`
     * about who La Cupida is ended up on the page as what the reader wanted.
     *
     * The passes are here too and named as passes. They are weaker evidence
     * than a like and the scoring already treats them that way; what they buy
     * in the prompt is a model that does not reach for a genre the reader
     * turned down to explain the choice.
     *
     * Handing the list over is not the same as asking for it back. Read out, it
     * is a receipt for eighteen swipes the reader has just made, and it was:
     * every line came back as "porque dijiste que sí a X, a Y y a Z", card
     * labels and all -- including the moods, which are written in the reader's
     * own mouth ("que me tenga en vilo") and so came back ungrammatical too.
     * `baseInstructions()` and `promptFor()` both say what it is for.
     */
    public function answers(): string
    {
        $lines = [];

        if ($this->likes !== []) {
            $lines[] = 'Ha dicho que sí a: ' . implode(', ', $this->likes) . '.';
        }

        if ($this->passes !== []) {
            $lines[] = 'Y que no a: ' . implode(', ', $this->passes) . '.';
        }

        return implode("\n", $lines);
    }

    /**
     * The books, small enough to read: title, who wrote it, and the first part
     * of the synopsis.
     *
     * The full synopses are several hundred words each and thirty of them would
     * be most of the prompt, for a decision that is made on the first line or
     * two. The shortlist has already done the matching that the long text would
     * inform.
     *
     * The keys are English even though everything the model writes is Spanish.
     * Prose is Spanish because the shop is; identifiers are English because the
     * code is, and a field name is an identifier wherever it ends up. The model
     * reads either without noticing, which is why the schema above names its
     * fields the same way.
     */
    public function catalog(): string
    {
        $lines = array_map(
            fn(array $book): array => [
                'ean'       => (string)$book['ean'],
                'title'     => $book['title'] ?? null,
                'author'    => $book['author'] ?? null,
                'publisher' => $book['publisher'] ?? null,
                'synopsis'  => is_string($book['synopsis'] ?? null)
                    ? (string)str($book['synopsis'])->squish()->limit(400)
                    : null,
            ],
            $this->shortlist,
        );

        return (string)json_encode($lines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<int, string>
     */
    private function eans(): array
    {
        return array_values(array_map(
            fn(array $book): string => (string)$book['ean'],
            $this->shortlist,
        ));
    }
}
