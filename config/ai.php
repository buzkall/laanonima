<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI provider
    |--------------------------------------------------------------------------
    |
    | The package ships with fifteen providers configured. This file keeps the
    | one the app actually talks to, because a provider block with no key is not
    | inert -- it is a name an agent can be pointed at by accident, and it fails
    | at the provider rather than here.
    |
    | Add a block back from vendor/laravel/ai/config/ai.php if a second one is
    | ever needed; the SDK can fail over between them.
    |
    | Everything the app asks of a model today is one short piece of Spanish
    | prose (see App\Ai\Agents\CupidaAgent), so there is nothing here for
    | images, audio, embeddings or reranking.
    |
    */

    'default' => env('AI_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | With no key set nothing throws: RecommendBook checks for one before it
    | prompts and falls back to picking the top of its own shortlist. So a
    | checkout with no credentials, and the test suite, both get a working page
    | with a canned line instead of a written one.
    |
    */

    'providers' => [

        'anthropic' => [
            'driver' => 'anthropic',
            'key'    => env('ANTHROPIC_API_KEY'),
            'url'    => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Nothing embeds anything yet. Left in place, off, because the SDK reads it.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

];
