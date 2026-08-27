<?php

use Illuminate\Support\Arr;

it('runs in Spanish by default', function() {
    expect(config('app.locale'))->toBe('es');
});

it('translates every language file into Spanish', function(string $file) {
    $spanish = require lang_path("es/{$file}.php");
    $english = require lang_path("en/{$file}.php");

    expect(array_keys(Arr::dot($spanish)))
        ->toEqualCanonicalizing(array_keys(Arr::dot($english)));
})->with(fn() => collect(glob(__DIR__ . '/../../lang/en/*.php'))
    ->map(fn(string $file): string => basename($file, '.php'))
    ->all());

it('resolves the user resource labels in Spanish', function() {
    expect(__('user.fields.name'))->toBe('Nombre')
        ->and(__('user.fields.email'))->toBe('Correo electrónico')
        ->and(__('user.policy.cannot_delete_self.all'))->toBe('No puedes eliminar tu propia cuenta.');
});

it('translates validation messages into Spanish', function() {
    expect(__('validation.required', ['attribute' => 'nombre']))
        ->toBe('El campo nombre es obligatorio.');
});
