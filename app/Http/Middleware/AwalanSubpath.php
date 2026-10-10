<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akses tanpa reverse proxy (mis. port langsung) tidak membawa X-Forwarded-Prefix; tanpa awalan itu URL relatif
 * (mis. URL update Livewire) tergandakan karena root URL sudah dipaksa berawalan (STANDAR-TEKNIS §2.4).
 * Header bawaan hanya berpengaruh bila pengirim termasuk proxy tepercaya.
 */
class AwalanSubpath
{
    public function handle(Request $request, Closure $next): Response
    {
        $awalan = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($awalan !== '' && ! $request->headers->has('X-Forwarded-Prefix')) {
            $request->headers->set('X-Forwarded-Prefix', $awalan);
        }

        return $next($request);
    }
}
