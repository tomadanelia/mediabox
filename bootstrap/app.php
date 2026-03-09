<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\IpWhiteList;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php', 
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();     
        $middleware->alias([
            'whitelist.ip' => IpWhiteList::class,
        ]);
        $middleware->trustProxies(at: '*'); 
        $middleware->validateCsrfTokens(except: [
            'api/admin/categories',    
            'api/admin/categories/*',
            'api/admin/plans',
            'api/admin/plans/*',
            'api/broadcasting/auth',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();