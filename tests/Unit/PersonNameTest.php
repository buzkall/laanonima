<?php

use App\Support\PersonName;

it('prints a shouted catalog name the way a reader would read it', function(string $filed, string $printed): void {
    expect(PersonName::normalize($filed))->toBe($printed);
})->with([
    /* What the lookup filed for 9788433950857, and what it should have said. */
    ['IAN. MCEWAN', 'Ian McEwan'],
    ['GABRIEL GARCÍA MÁRQUEZ', 'Gabriel García Márquez'],
    ['JEAN-PAUL SARTRE', 'Jean-Paul Sartre'],
    ["FLANN O'BRIEN", "Flann O'Brien"],
    ['  ANA   MARÍA  MATUTE  ', 'Ana María Matute'],
]);

it('leaves a particle lowercase, but never the first word', function(): void {
    expect(PersonName::normalize('JOSÉ DE ESPRONCEDA'))->toBe('José de Espronceda')
        ->and(PersonName::normalize('DE LA FUENTE'))->toBe('De la Fuente');
});

it('keeps the periods that belong to initials', function(): void {
    expect(PersonName::normalize('J. R. R. TOLKIEN'))->toBe('J. R. R. Tolkien')
        ->and(PersonName::normalize('VV. AA.'))->toBe('VV. AA.');
});

/*
 | A source that wrote any lowercase letter at all had a reason for every
 | capital it did write, and no rule recovers "McEwan" or "de la Fuente" once
 | they have been flattened.
 */
it('does not recase a name that was cased on purpose', function(): void {
    expect(PersonName::normalize('Ian McEwan'))->toBe('Ian McEwan')
        ->and(PersonName::normalize('Ursula K. Le Guin'))->toBe('Ursula K. Le Guin')
        ->and(PersonName::normalize('de la Fuente, Ana'))->toBe('de la Fuente, Ana');
});
