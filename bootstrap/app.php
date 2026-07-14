<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'erp.api' => \App\Http\Middleware\ErpApiMiddleware::class,
        ]);

        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);

        // Permitir que la sincronización ERP funcione incluso en modo mantenimiento
        $middleware->preventRequestsDuringMaintenance(except: [
            'api/sync/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

$app->usePublicPath(__DIR__.'/../public_html');

return $app;
