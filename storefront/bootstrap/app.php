<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every host this can be deployed to terminates TLS in front of the
        // app and forwards plain HTTP. Without this the request looks like
        // http:// from inside, asset() writes http:// URLs into an https://
        // page, and the browser blocks all of them as mixed content — the
        // page arrives with no stylesheets at all. The proxy is the platform's
        // own and is the only thing that can reach the container, so trusting
        // its headers is trusting the platform.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
