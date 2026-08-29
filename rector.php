<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Php85\Rector\Property\AddOverrideAttributeToOverriddenPropertiesRector;
use Rector\Privatization\Rector\Class_\FinalizeTestCaseClassRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/bootstrap/app.php',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->withCache(__DIR__ . '/storage/framework/cache/rector')
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withComposerBased(laravel: true)
    ->withSets([
        PestSetList::CODING_STYLE,
    ])
    ->withImportNames(removeUnusedImports: true)
    ->withSkip([
        // Pint owns formatting; never let Rector fight it.
        __DIR__ . '/bootstrap/cache',

        /*
         | A gitignored, developer-local convenience file, and on at least one
         | machine a symlink out to Dropbox -- Rector follows it and rewrites a
         | file that is not in this repository and that CI never sees.
         */
        __DIR__ . '/routes/autologin.php',

        // Livewire/Filament hydrate public properties by reflection.
        ReadOnlyPropertyRector::class,

        // No #[Override] attributes anywhere. Laravel, Filament and Livewire
        // are override-heavy by design, so the attribute would land on a large
        // share of methods for no reader benefit, and it turns a parent-side
        // rename during a framework or package upgrade into a fatal error.
        AddOverrideAttributeToOverriddenMethodsRector::class,
        AddOverrideAttributeToOverriddenPropertiesRector::class,

        // Filament static props carry union types (string|BackedEnum|null)
        // that constructor promotion would break.
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__ . '/app/Filament',
        ],
        FinalizeTestCaseClassRector::class,

        // Opt-in, not a side effect of setting up Rector: no file in this
        // project declares strict_types today, and turning it on across
        // config/ changes scalar coercion at runtime, not at test time.
        // Drop this line deliberately if the project adopts strict types.
        SafeDeclareStrictTypesRector::class,
    ]);
