<?php

use Bugsnag\BugsnagLaravel\BugsnagServiceProvider;
use Monolog\Handler\PsrHandler;

it('registers the bugsnag service provider', function() {
    expect(app()->getLoadedProviders())->toHaveKey(BugsnagServiceProvider::class);
});

it('resolves the bugsnag log channel without falling back to the emergency logger', function() {
    $handlers = Log::channel('bugsnag')->getLogger()->getHandlers();

    expect($handlers[0])->toBeInstanceOf(PsrHandler::class);
});
