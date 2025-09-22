<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'widget/*',
            'bitrix24/*',
            'webhooks/*'
        ]);
        $middleware->alias([
            'organization.access' => \App\Http\Middleware\EnsureUserBelongsToOrganization::class,
            'bot.access' => \App\Http\Middleware\CheckBotAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withCommands([
        \App\Console\Commands\GenerateEmbeddings::class,
    ])
    ->create();
