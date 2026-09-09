<?php

use App\Notifications\CupidaCreditExhausted;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;

/*
 | Words that only turn up when a string addresses the reader as "usted".
 | Deliberately narrow: a subjunctive such as "antes de que se verifique" is
 | impersonal, not formal, and must not trip this.
 */
const FORMAL_ADDRESS = '/\b(usted|inténtelo|intente|compruebe|desea|póngase|contáctenos)\b|\bseleccione\b|\bintroduzca\b|\bsu (cuenta|contraseña|búsqueda|dirección|correo|identidad)\b/iu';

it('runs in Spanish by default', function(): void {
    expect(config('app.locale'))->toBe('es');
});

it('translates every language file into Spanish', function(string $file): void {
    $spanish = require lang_path("es/{$file}.php");
    $english = require lang_path("en/{$file}.php");

    expect(array_keys(Arr::dot($spanish)))
        ->toEqualCanonicalizing(array_keys(Arr::dot($english)));
})->with(fn() => collect(glob(__DIR__ . '/../../lang/en/*.php'))
    ->map(fn(string $file): string => basename($file, '.php'))
    ->all());

it('resolves the user resource labels in Spanish', function(): void {
    expect(__('user.fields.name'))->toBe('Nombre')
        ->and(__('user.fields.email'))->toBe('Correo electrónico')
        ->and(__('user.policy.cannot_delete_self.all'))->toBe('No puedes eliminar tu propia cuenta.');
});

it('translates validation messages into Spanish', function(): void {
    expect(__('validation.required', ['attribute' => 'nombre']))
        ->toBe('El campo nombre es obligatorio.');
});

/*
 | Filament's own Spanish addresses the reader as "usted" ("Entre a su
 | cuenta"). The shop speaks to them as "tú", so lang/vendor holds a partial
 | override per package file. These two tests are what keeps that from rotting:
 | a key upstream renames stops overriding anything silently, and a formal
 | phrase written by hand goes unnoticed.
 */

it('speaks to readers as tú, not usted', function(): void {
    expect(__('filament-panels::auth/pages/login.heading'))->toBe('Entra a tu cuenta')
        ->and(__('filament-panels::auth/pages/register.actions.login.label'))->toBe('entra en tu cuenta')
        ->and(__('filament-panels::auth/pages/edit-profile.form.current_password.below_content'))
        ->toBe('Por seguridad, confirma tu contraseña para continuar.')
        ->and(__('filament-forms::components.select.placeholder'))->toBe('Selecciona una opción')
        ->and(__('filament-tables::table.columns.select.placeholder'))->toBe('Selecciona una opción');
});

it('overrides only keys the package still defines, with no formal address left', function(string $file): void {
    [$namespace, $group] = translationOverrideTarget($file);

    $hint = app('translator')->getLoader()->namespaces()[$namespace] ?? null;

    expect($hint)->not->toBeNull("No package registers the [{$namespace}] translation namespace.");

    $package = require "{$hint}/es/{$group}.php";
    $overrides = Arr::dot(require $file);

    expect($overrides)->not->toBeEmpty();

    foreach ($overrides as $key => $value) {
        expect(Arr::has($package, $key))
            ->toBeTrue("[{$namespace}::{$group}.{$key}] no longer exists upstream, so the override does nothing.")
            ->and($value)
            ->not->toMatch(FORMAL_ADDRESS);
    }
})->with(fn(): array => translationOverrideFiles());

/*
 | The chrome of a notification mail -- greeting, salutation, the "copy this
 | URL" subcopy, the footer -- comes from Laravel's own view, which asks for
 | those strings by their English text. Without lang/es.json a Spanish mail
 | still opens with "Hello!" and signs off "Regards,".
 |
 | The subcopy line is the exception: finisterre registers its lang directory
 | AFTER the app's, so its own es.json wins for that one key and lang/es.json
 | must not restate it. The assertion below pins finisterre's wording so an
 | upstream rewording is noticed here rather than in a reader's inbox.
 */

it('translates the notification mail chrome into Spanish', function(): void {
    expect(__('Hello!'))->toBe('¡Hola!')
        ->and(__('Whoops!'))->toBe('¡Vaya!')
        ->and(__('Regards,'))->toBe('Un saludo,')
        ->and(__('All rights reserved.'))->toBe('Todos los derechos reservados.')
        ->and(__("If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:", ['actionText' => 'Ver La Cupida']))
        ->toBe('Si tienes problemas para hacer clic en el botón "Ver La Cupida", copia y pega esta URL en tu navegador:');
});

it('renders a notification mail with no English left in its chrome', function(): void {
    $notifiable = Notification::route('mail', 'admin@example.com');

    $rendered = (string)new CupidaCreditExhausted()->toMail($notifiable)->render();

    expect($rendered)->toContain('¡Hola!')
        ->and($rendered)->toContain('Un saludo,')
        ->and($rendered)->not->toContain('Hello!')
        ->and($rendered)->not->toContain('Regards,');
});
