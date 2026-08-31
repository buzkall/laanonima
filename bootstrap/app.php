<?php

use App\Enums\UserRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function(Middleware $middleware): void {
        /* Nothing on the shop is behind `auth` except asking us for a book, and
           a reader doing that is a client: send them to the client panel's login
           rather than to a `login` route this application does not have. */
        $middleware->redirectGuestsTo(fn(): string => UserRole::Client->loginUrl());
    })
    ->withExceptions(function(Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
