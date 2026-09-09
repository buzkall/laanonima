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
 * matches is *the* one, and say why in a way that makes somebody want to read
 * it.
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
     * @param  array<int, array<string, mixed>>  $shortlist  scored books, best first
     */
    public function __construct(public array $shortlist) {}

    /**
     * The baseline, plus whatever the shop has added in the panel.
     *
     * The two are kept apart on purpose. Everything below is what makes the
     * recommendation trustworthy -- choose from what you are given, invent
     * nothing, do not claim to be a person -- and it is in code so that it can
     * only change with a deployment and a review. What a bookseller writes in the
     * panel is taste: the season, the table by the door, the writer they are
     * pushing this month. It is appended, and it is announced as coming from
     * the shop, so it reads as more of the brief rather than as a license to
     * ignore the brief.
     */
    public function instructions(): Stringable|string
    {
        /* Resolved on every prompt rather than injected once: a bookseller who
           saves the modal expects the next swipe to use it, not the next
           deploy. */
        $extra = trim((string)app(CupidaSettings::class)->extra_instructions) ?: null;

        return $extra === null
            ? $this->baseInstructions()
            : $this->baseInstructions() . "\n\nY esto es lo que pide la librería esta temporada:\n\n{$extra}";
    }

    public function baseInstructions(): string
    {
        return <<<'PROMPT'
        Eres la librera de La Anónima, una librería independiente de Arganzuela, en Madrid,
        especializada en autoras y editoriales independientes. Alguien acaba de responder a
        tres preguntas deslizando cartas: ha dicho que sí a unos géneros, a unas autoras y
        autores, y a unos estados de ánimo, y que no a otros.

        Te doy una lista de libros que están en la librería ahora mismo. Elige UNO y
        explícale por qué es ese.

        Escribe en español de España y solo en español de España: la recomendación
        entera, hasta la última palabra. Los libros que te doy están en español y sus
        sinopsis también; no las traduzcas ni las parafrasees en otro idioma. Una sola
        frase en inglés echa a perder la respuesta.

        Cómo escribir:

        - Tuteando. Nunca de usted.
        - Como habla una librera que ha leído el libro, no como una ficha ni como un anuncio.
          Concreta: di qué pasa en el libro o cómo se lee, no que es "una obra imprescindible".
        - Dos o tres frases para la recomendación. Ni una más.
        - Nunca digas que eres una inteligencia artificial, ni menciones listas, puntuaciones
          ni que te han dado nada a elegir.
        - No inventes títulos, autorías ni argumentos: usa solo lo que te doy.
        - No repitas el título dentro del texto de la recomendación; ya se ve encima.

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
     * given -- and `match_line`, whose description pins how it starts, stayed in
     * Spanish through the same answer. What is written next to the field is what
     * the field is written against, so the language belongs in both places.
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
                ->description('En español. Dos o tres frases contándole por qué le va a gustar. Sin repetir el título.')
                ->required(),

            'match_line' => $schema->string()
                ->description('En español. Una sola línea corta diciendo con qué respuestas suyas encaja, empezando por "Porque".')
                ->required(),
        ];
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
