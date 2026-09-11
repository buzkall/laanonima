<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Gate;

/**
 * The rule this file exists to enforce: a model with a Filament resource has a
 * policy, and that policy answers every question the panel asks of it.
 *
 * Filament allows what a policy does not mention -- a missing `delete()` reads
 * as "yes" and not as "nobody has decided yet" -- so a resource added without
 * one is open to every account that reaches its panel, silently and with
 * nothing in the diff to notice. Adding the resource is what makes this fail;
 * writing the policy is what fixes it.
 *
 * Every panel is walked rather than the admin one alone: the same model can be
 * exposed twice over -- book requests are -- and each panel is its own way in.
 *
 * The resources are read inside the tests and not as a dataset, because a Pest
 * dataset is resolved before the application boots and `Filament` has no facade
 * root that early.
 *
 * @return array<class-string<resource>>
 */
function filamentResources(): array
{
    return collect(Filament::getPanels())
        ->flatMap(fn(Panel $panel): array => $panel->getResources())
        ->unique()
        ->values()
        ->all();
}

it('has a policy for every model a resource exposes', function(): void {
    $missing = collect(filamentResources())
        ->reject(fn(string $resource): bool => filled(Gate::getPolicyFor($resource::getModel())))
        ->map(fn(string $resource): string => class_basename($resource::getModel()) . 'Policy')
        ->values()
        ->all();

    expect($missing)->toBe([], 'Every model behind a Filament resource needs a policy: Filament allows whatever a policy does not mention.');
});

it('leaves no ability for a resource to decide by default', function(): void {
    $undecided = collect(filamentResources())
        ->flatMap(function(string $resource): array {
            $policy = Gate::getPolicyFor($resource::getModel());

            /* A model with no policy at all is the test above's to report. */
            if (blank($policy)) {
                return [];
            }

            return collect(['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny'])
                ->reject(fn(string $ability): bool => method_exists($policy, $ability))
                ->map(fn(string $ability): string => $policy::class . "::{$ability}()")
                ->all();
        })
        ->all();

    expect($undecided)->toBe([], 'An ability a policy does not mention is one Filament reads as "allowed".');
});

/**
 * The two tests above pass just as happily on an empty list, so this one says
 * the list is not empty. No fixed count: a resource added is exactly the thing
 * this file is meant to catch, and it should fail for the missing policy rather
 * than for a number nobody updated.
 */
it('is actually looking at every panel', function(): void {
    expect(Filament::getPanels())->not->toBeEmpty();

    foreach (Filament::getPanels() as $id => $panel) {
        expect($panel->getResources())->not->toBeEmpty("Panel [{$id}] exposes no resources.");
    }
});
