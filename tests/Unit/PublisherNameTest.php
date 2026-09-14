<?php

use App\Support\PublisherName;

it('prints a shouted imprint the way the spine does', function(string $listed, string $printed): void {
    expect(PublisherName::normalize($listed))->toBe($printed);
})->with([
    /* Every one of these is a name the shop's own listing shouts. */
    ['ALFAGUARA', 'Alfaguara'],
    ['ASTIBERRI EDICIONES', 'Astiberri Ediciones'],
    ['ANAYA EDUCACIÓN', 'Anaya Educación'],
    ['BABIDI-BÚ', 'Babidi-Bú'],
    ['DIEGO PUN EDICIONES', 'Diego Pun Ediciones'],
    ['  MILKY   WAY  EDICIONES  ', 'Milky Way Ediciones'],
]);

it('leaves a particle lowercase, but never the first word', function(): void {
    expect(PublisherName::normalize('CABALLO DE TROYA'))->toBe('Caballo de Troya')
        ->and(PublisherName::normalize('LAS AFUERAS'))->toBe('Las Afueras')
        ->and(PublisherName::normalize('ROSITA Y AMPARO'))->toBe('Rosita y Amparo')
        ->and(PublisherName::normalize('LA ESFERA DE LOS LIBROS'))->toBe('La Esfera de los Libros');
});

/*
 | The half of a publisher's name that is not words at all. Title case turns
 | "S.L." into "S.l." and "DK" into "Dk", which is a worse listing than the
 | shouting was.
 */
it('keeps legal forms, initials and everything that is not a word', function(string $listed, string $printed): void {
    expect(PublisherName::normalize($listed))->toBe($printed);
})->with([
    ['NORMA EDITORIAL, S.A.', 'Norma Editorial, S.A.'],
    ['SATORI EDICIONES C.B.', 'Satori Ediciones C.B.'],
    ['LIBROS DEL K.O', 'Libros del K.O'],
    ['FERA EDICIONES SL', 'Fera Ediciones SL'],
    ['LIBROS DEL KO, SLL', 'Libros del KO, SLL'],
    ['EDICIONES SM', 'Ediciones SM'],
    ['EDICIONES T&T', 'Ediciones T&T'],
    ['PPC EDITORIAL', 'PPC Editorial'],
    ['PLAZA & JANES', 'Plaza & Janes'],
    ['CLUB EDITOR 1959, S.L.', 'Club Editor 1959, S.L.'],
    ['EDITORIAL BASE (ES)', 'Editorial Base (ES)'],
    ['UVE BOOKS', 'UVE Books'],
    ['UNED', 'UNED'],
    ['DK', 'DK'],
    ['B', 'B'],
]);

/*
 | A comma with no space after it is the only reason this name is one word, and
 | the legal form on the far side of it still has to survive.
 */
it('reads a missing space after a comma as the word break it is', function(): void {
    expect(PublisherName::normalize('EDITORIAL ELBA,S.L.'))->toBe('Editorial Elba,S.L.');
});

/*
 | A source that wrote any lowercase letter at all had a reason for every
 | capital it did write: "AdN", "Alpha decay" and "Duomo ediciones" are all how
 | those imprints spell themselves.
 */
it('does not recase a name that was cased on purpose', function(): void {
    expect(PublisherName::normalize('Duomo ediciones'))->toBe('Duomo ediciones')
        ->and(PublisherName::normalize('AdN Editorial Grupo Anaya'))->toBe('AdN Editorial Grupo Anaya')
        ->and(PublisherName::normalize('Alpha decay'))->toBe('Alpha decay');
});

/*
 | The shop's publisher links arrive double-encoded, so the scrape writes down
 | an entity where an ampersand belongs -- in a name that is otherwise cased
 | perfectly well.
 */
it('decodes the entity the shop double-encoded', function(): void {
    expect(PublisherName::normalize('Blatt &amp; Ríos'))->toBe('Blatt & Ríos')
        ->and(PublisherName::normalize('Col&amp;Col Ediciones'))->toBe('Col&Col Ediciones');
});
