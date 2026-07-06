<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
        $middleware->redirectGuestsTo(fn () => route('login', absolute: false));
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\EnforceSessionTimeout::class,
        ]);
        $middleware->append([
            \App\Http\Middleware\AddSecurityHeaders::class,
        ]);

        $middleware->alias([
            'two-factor' => \App\Http\Middleware\EnsureTwoFactorVerified::class,
            'password-changed' => \App\Http\Middleware\EnsurePasswordIsChanged::class,
            'assigned-center' => \App\Http\Middleware\EnsureAssignedCenter::class,
            'owner-active-center' => \App\Http\Middleware\EnsureOwnerActiveCenter::class,
            'owner' => \App\Http\Middleware\EnsureOwner::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
