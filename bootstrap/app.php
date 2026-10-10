<?php

use App\Http\Middleware\AwalanSubpath;
use App\Http\Middleware\HeaderKeamanan;
use App\Http\Responses\GalatJsonApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AwalanSubpath::class);
        $middleware->append(HeaderKeamanan::class);

        // Tamu API tidak dialihkan ke halaman masuk: 401 JSON (BR-18); selain itu ke rute login (bila ada) atau
        // halaman masuk panel akun.
        $middleware->redirectGuestsTo(fn (Request $request): ?string => match (true) {
            $request->is('api/*') => null,
            Route::has('login') => route('login'),
            default => route('filament.akun.auth.login'),
        });

        // Ability token Sanctum untuk API baca (BR-18).
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // Daftar proxy diatur di AppServiceProvider dari config('regulasi.trusted_proxies').
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // API v1 memakai format galat JSON:API (401/403/404/422/429).
        $exceptions->render(fn (Throwable $galat, Request $request) => $request->is('api/v1/*') ? GalatJsonApi::dari($galat) : null);
    })->create();
