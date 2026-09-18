<?php

use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\EnsureJsonApiRequest;
use App\Http\Middleware\EnsureTrustedHost;
use App\Http\Middleware\IdempotencyKey;
use App\Http\Middleware\LogApiRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetRequestLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnsureTrustedHost::class);

        $middleware->api(prepend: [
            AttachRequestId::class,
            SetRequestLocale::class,
        ]);

        $middleware->api(append: [
            EnsureJsonApiRequest::class,
            LogApiRequests::class,
            IdempotencyKey::class,
        ]);

        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
